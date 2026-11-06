<?php

use App\Actions\Reports\GenerateMonthlyReports;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Jobs\GenerateMonthlyReport;
use App\Livewire\Reports\Index;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Models\WorkOrder;
use App\Services\MonthlyReportBuilder;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    Storage::fake('local');
    $this->w = workOrderWorld();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 00:30:00', 'UTC')); // 06:00 IST on the 1st
    $this->may = CarbonImmutable::parse('2026-05-01', 'Asia/Kolkata');
});

it('queues one job per active client as a batch on the reports queue', function () {
    Bus::fake();
    Client::factory()->forTenant($this->w->tenant)->create();
    Client::factory()->forTenant($this->w->tenant)->create(['is_active' => false]);

    app(GenerateMonthlyReports::class)->handle($this->w->tenant, $this->may, $this->w->admin);

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
        && $batch->queue() === 'reports'
        && $batch->allowsFailures()
        && $batch->jobs->every(fn ($job) => $job instanceof GenerateMonthlyReport && $job->tenantId === $this->w->tenant->id));

    expect(Report::where('status', 'pending')->count())->toBe(2)
        ->and(Report::first()->requested_by)->toBe($this->w->admin->id);
});

it('does not queue a client twice for the same month', function () {
    Bus::fake();

    app(GenerateMonthlyReports::class)->handle($this->w->tenant, $this->may);
    $second = app(GenerateMonthlyReports::class)->handle($this->w->tenant, $this->may);

    expect($second)->toBeNull()->and(Report::count())->toBe(1);
    Bus::assertBatchCount(1);
});

it('generates the PDF and Excel for a client', function () {
    $wo = WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Resolved)->create([
        'title' => '=HYPERLINK("http://evil")', 'created_at' => '2026-05-10 05:00:00', 'resolved_at' => '2026-05-10 09:00:00',
    ]);
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-04-10 05:00:00']); // other month
    $otherClientSite = Site::factory()->create(['tenant_id' => $this->w->tenant->id]);
    WorkOrder::factory()->forSite($otherClientSite)->create(['created_at' => '2026-05-10 05:00:00']); // other client

    app(GenerateMonthlyReports::class)->handle($this->w->tenant, $this->may); // sync queue in tests

    $report = Report::where('client_id', $this->w->client->id)->sole();
    expect($report->status)->toBe(ReportStatus::Completed)
        ->and($report->completed_at)->not->toBeNull()
        ->and($report->pdf_path)->toStartWith("tenants/{$this->w->tenant->id}/reports/2026-05/");

    expect(Storage::disk('local')->get($report->pdf_path))->toStartWith('%PDF');

    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($path, Storage::disk('local')->get($report->xlsx_path));
    $sheet = IOFactory::load($path)->getActiveSheet()->toArray();
    unlink($path);

    expect($sheet)->toHaveCount(2)
        ->and($sheet[0][0])->toBe('Number')
        ->and($sheet[1][0])->toBe($wo->number)
        ->and($sheet[1][5])->toBe("'=HYPERLINK(\"http://evil\")");
});

it('summarises the month in the tenant\'s timezone', function () {
    WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Resolved)->create(['created_at' => '2026-04-30 20:00:00', 'resolved_at' => '2026-05-01 00:00:00']); // 1 May IST
    WorkOrder::factory()->forSite($this->w->site)->status(WorkOrderStatus::Closed)->create(['created_at' => '2026-05-03 05:00:00', 'resolved_at' => '2026-05-03 09:00:00', 'is_resolution_breached' => true]);
    WorkOrder::factory()->forSite($this->w->site)->create(['created_at' => '2026-05-31 19:00:00']); // 1 June IST

    $data = app(MonthlyReportBuilder::class)->build($this->w->tenant, $this->w->client, $this->may);

    expect($data['totals'])->toMatchArray([
        'raised' => 2,
        'resolved' => 2,
        'closed' => 1,
        'resolution_breached' => 1,
        'resolution_compliance' => 50.0,
        'avg_resolution_hours' => 4.0,
    ]);
});

it('runs on the 1st in each tenant\'s timezone, once', function () {
    Bus::fake();
    $newYork = tenantWithRoles(['timezone' => 'America/New_York']); // still 31 May there
    Client::factory()->forTenant($newYork)->create();
    app(TenantContext::class)->clear();

    $this->artisan('reports:monthly')->expectsOutputToContain("{$this->w->tenant->slug} 2026-05: 1 report(s) queued")->assertSuccessful();
    $this->artisan('reports:monthly')->expectsOutputToContain('nothing to do');

    Bus::assertBatchCount(1);
    expect(Report::withoutGlobalScope('tenant')->where('tenant_id', $newYork->id)->count())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2026-06-01 05:00:00', 'UTC')); // 1 June 01:00 in New York
    $this->artisan('reports:monthly')->expectsOutputToContain("{$newYork->slug} 2026-05: 1 report(s) queued");
});

it('can be run for a given month', function () {
    Bus::fake();
    app(TenantContext::class)->clear();
    $this->travelTo(CarbonImmutable::parse('2026-06-15 06:00:00', 'UTC'));

    $this->artisan('reports:monthly', ['--month' => '2026-03'])->expectsOutputToContain('2026-03: 1 report(s) queued');
});

it('is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'reports:monthly'));

    expect($event->expression)->toBe('0 * * * *');
});

it('marks a report failed without stopping the batch', function () {
    $report = Report::query()->forceCreate(['tenant_id' => $this->w->tenant->id, 'client_id' => $this->w->client->id, 'month' => '2026-05-01']);

    (new GenerateMonthlyReport($report->id, $this->w->tenant->id))->failed(new RuntimeException('Disk full'));

    expect($report->fresh())->status->toBe(ReportStatus::Failed)->error->toBe('Disk full');
});

it('lets managers generate and download reports from the page', function () {
    Livewire::actingAs($this->w->supervisor)->test(Index::class)
        ->assertSet('month', '2026-05')
        ->call('generate')
        ->assertSet('flash', 'Generating 1 report(s)…')
        ->assertSee('Ready');

    $report = Report::sole();
    $this->actingAs($this->w->supervisor);

    $this->get($report->downloadUrl('pdf'))->assertOk()->assertDownload(str($this->w->client->name)->slug().'-2026-05.pdf');
    $this->get($report->downloadUrl('xlsx'))->assertOk();
    $this->get($this->w->tenant->url("reports/{$report->id}/pdf"))->assertForbidden(); // unsigned
});

it('keeps reports away from technicians and other tenants', function () {
    app(GenerateMonthlyReports::class)->handle($this->w->tenant, $this->may);
    $report = Report::sole();

    $this->actingAs($this->w->technician)->get($this->w->tenant->url('reports'))->assertForbidden();
    $this->actingAs($this->w->technician)->get($report->downloadUrl('pdf'))->assertForbidden();

    $other = tenantWithRoles();
    $this->actingAs(userWithRole(UserRole::TenantAdmin, $other))
        ->get(URL::temporarySignedRoute('reports.download', now()->addMinute(), ['tenant' => $other->slug, 'report' => $report->id, 'format' => 'pdf']))
        ->assertNotFound();
});
