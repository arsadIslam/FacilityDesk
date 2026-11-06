<?php

namespace Database\Seeders;

use App\Actions\Tenants\RegisterTenant;
use App\Enums\AssetStatus;
use App\Enums\LocationType;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\WorkOrderSource;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Site;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Two demo workspaces with one login per role (password "password"), 5
 * clients, 12 sites with location trees and assets, and ~200 work orders
 * across every status, some of them breached.
 *
 * With $large (php artisan demo:seed --large) it adds 50,000 work orders to
 * Acme with bulk inserts, for benchmarking the list and dashboard queries.
 *
 * Rows are written directly rather than through the Actions so seeding does
 * not queue thousands of notifications.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const LARGE_COUNT = 50_000;

    public bool $large = false;

    /** @var array<string, string> title => default category */
    private const TITLES = [
        'AC not cooling' => 'HVAC', 'AC unit making loud noise' => 'HVAC', 'Server room too warm' => 'HVAC',
        'Water leakage near pantry' => 'Plumbing', 'Washroom tap broken' => 'Plumbing', 'Drain blocked' => 'Plumbing', 'Water dispenser leaking' => 'Plumbing',
        'Lights flickering' => 'Electrical', 'Power socket sparking' => 'Electrical', 'Generator not starting' => 'Electrical', 'Fire exit sign not lit' => 'Electrical',
        'Lift stuck between floors' => 'Civil', 'Ceiling tile damaged' => 'Civil', 'Glass door hinge loose' => 'Civil',
        'Pest sighting in cafeteria' => 'Housekeeping', 'Spill in lobby' => 'Housekeeping',
        'Door access card reader down' => 'Security', 'CCTV camera offline' => 'Security',
    ];

    public function run(): void
    {
        // Only the signup verification mail needs silencing: work orders are written
        // directly, so no work order events fire. A bare Event::fake() would also
        // swallow Eloquent model events (tenant_id stamping, QR tokens).
        Event::fake([Registered::class]);
        mt_srand(2026);

        $acme = $this->workspace('acme', 'Acme Facilities', 'Asia/Kolkata', [
            'Globex Corp' => ['Globex Tower', 'Globex Labs', 'Globex Warehouse'],
            'Initech' => ['Initech HQ', 'Initech Data Centre', 'Initech Annex'],
            'Umbrella Health' => ['Umbrella Clinic North', 'Umbrella Clinic South'],
        ], 150);

        $this->workspace('globex', 'Northwind Services', 'Asia/Dubai', [
            'Stark Retail' => ['Stark Mall', 'Stark Outlet'],
            'Wayne Estates' => ['Wayne Plaza', 'Wayne Residency'],
        ], 50);

        if ($this->large) {
            $this->largeVolume($acme, self::LARGE_COUNT);
        }

        app(TenantContext::class)->clear();
    }

    /**
     * @param  array<string, list<string>>  $clients  client => site names
     */
    private function workspace(string $slug, string $name, string $timezone, array $clients, int $workOrders): Tenant
    {
        $context = app(TenantContext::class);

        $tenant = app(RegisterTenant::class)->handle($name, $slug, ucfirst($slug).' Admin', "admin@{$slug}.test", self::PASSWORD);
        $tenant->forceFill(['timezone' => $timezone])->save();
        $context->set($tenant);

        User::query()->update(['email_verified_at' => now()]);

        $people = [
            'supervisor' => $this->user($tenant, UserRole::Supervisor, "supervisor@{$slug}.test", 'Sara Supervisor'),
            'supervisor2' => $this->user($tenant, UserRole::Supervisor, "supervisor2@{$slug}.test", 'Sam Supervisor'),
            'technicians' => collect(['Tara', 'Tom', 'Tariq', 'Tina', 'Theo'])
                ->map(fn ($first, $i) => $this->user($tenant, UserRole::Technician, $i === 0 ? "tech@{$slug}.test" : "tech{$i}@{$slug}.test", "{$first} Technician")),
        ];
        $categories = Category::query()->get();

        $sites = collect();
        foreach ($clients as $clientName => $siteNames) {
            $client = new Client(['name' => $clientName, 'contact_person' => fake()->name(), 'email' => 'facilities@'.str($clientName)->slug().'.test']);
            $client->save();

            if ($sites->isEmpty()) {
                $clientUser = $this->user($tenant, UserRole::ClientUser, "client@{$slug}.test", 'Chris Client');
                $clientUser->forceFill(['client_id' => $client->id])->save();
            }

            foreach ($siteNames as $i => $siteName) {
                $site = new Site([
                    'client_id' => $client->id,
                    'name' => $siteName,
                    'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $siteName) ?? 'SITE', 0, 3)).'-'.str_pad((string) ($sites->count() + 1), 2, '0', STR_PAD_LEFT),
                    'city' => fake()->city(),
                    'timezone' => $timezone,
                    'supervisor_id' => $i % 2 === 0 ? $people['supervisor']->id : $people['supervisor2']->id,
                ]);
                $site->save();
                $site->coverage()->syncWithPivotValues($people['technicians']->random(3)->pluck('id')->all(), ['tenant_id' => $tenant->id]);
                $this->locationsAndAssets($site, $categories);
                $sites->push($site->load('locations'));
            }
        }

        foreach ($people['technicians'] as $technician) {
            $technician->skills()->syncWithPivotValues($categories->random(2)->modelKeys(), ['tenant_id' => $tenant->id]);
        }

        $this->workOrders($tenant, $sites, $categories, $people, $workOrders);

        return $tenant;
    }

    private function user(Tenant $tenant, UserRole $role, string $email, string $name): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]);
        $user->forceFill(['tenant_id' => $tenant->id, 'email_verified_at' => now()])->save();
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Block A / Ground + 1st floor / two rooms each, plus a few assets.
     *
     * @param  Collection<int, Category>  $categories
     */
    private function locationsAndAssets(Site $site, $categories): void
    {
        $make = function (string $name, LocationType $type, ?Location $parent) use ($site): Location {
            $location = new Location(['type' => $type, 'name' => $name]);
            $location->forceFill([
                'site_id' => $site->id,
                'parent_id' => $parent?->id,
                'path' => $parent === null ? $name : $parent->path.' / '.$name,
            ])->save();

            return $location;
        };

        $block = $make('Block A', LocationType::Building, null);
        $rooms = [];
        foreach (['Ground Floor', '1st Floor'] as $floorName) {
            $floor = $make($floorName, LocationType::Floor, $block);
            foreach (['Reception', 'Pantry', 'Meeting Room'] as $roomName) {
                $rooms[] = $make($roomName, LocationType::Room, $floor);
            }
        }

        foreach (['AC unit' => 'HVAC', 'Water pump' => 'Plumbing', 'DG set' => 'Electrical'] as $assetName => $categoryName) {
            $i = array_search($assetName, ['AC unit', 'Water pump', 'DG set'], true);
            $room = $rooms[$i];
            Asset::create([
                'site_id' => $site->id,
                'location_id' => $room->id,
                'category_id' => ($categories->firstWhere('name', $categoryName) ?? $categories->random())->id,
                'name' => "{$assetName} {$site->code}",
                'code' => "{$site->code}-A".($i + 1),
                'make' => fake()->company(),
                'model' => 'M-'.mt_rand(100, 999),
                'serial_no' => 'SN'.mt_rand(10000000, 99999999),
                'installed_at' => now()->subYears(2)->toDateString(),
                'warranty_until' => now()->addMonths(mt_rand(-6, 18))->toDateString(),
                'status' => $i === 2 ? AssetStatus::UnderRepair : AssetStatus::Working,
            ]);
        }
    }

    /**
     * Spread over the last 60 days, every status represented, with a status
     * history that matches each work order's state.
     *
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, Category>  $categories
     * @param  array<string, mixed>  $people
     */
    private function workOrders(Tenant $tenant, $sites, $categories, array $people, int $count): void
    {
        $policies = SlaPolicy::query()->get()->keyBy(fn (SlaPolicy $p) => $p->priority->value);
        $statuses = [
            WorkOrderStatus::Open->value => 12, WorkOrderStatus::Assigned->value => 15, WorkOrderStatus::InProgress->value => 15,
            WorkOrderStatus::OnHold->value => 5, WorkOrderStatus::Resolved->value => 13, WorkOrderStatus::Closed->value => 35, WorkOrderStatus::Cancelled->value => 5,
        ];
        $number = 0;

        for ($i = 0; $i < $count; $i++) {
            $status = WorkOrderStatus::from($this->weighted($statuses));
            $priority = Priority::cases()[mt_rand(0, 3)];
            $site = $sites->random();
            $policy = $policies[$priority->value];
            $technician = $people['technicians']->random();
            $late = mt_rand(1, 100) <= 15;

            // Finished work spreads over 60 days. Live work sits inside its SLA
            // window (response window until someone responds), except the ~15%
            // that are late, so the demo shows "some" breaches, not most.
            $window = in_array($status, [WorkOrderStatus::Open, WorkOrderStatus::Assigned], true)
                ? $policy->response_minutes
                : $policy->resolution_minutes;
            $raisedAt = CarbonImmutable::now()->subMinutes(match (true) {
                $status->isFinished() => mt_rand(60 * 24, 60 * 24 * 60),
                $late => (int) ($window * 1.2) + mt_rand(5, 60),
                default => mt_rand(1, max(2, (int) ($window * 0.8))),
            });

            $title = array_rand(self::TITLES);
            $wo = new WorkOrder(['title' => $title, 'description' => fake()->sentence(12)]);
            $wo->forceFill([
                // saveQuietly() below skips the creating hook that would stamp this.
                'tenant_id' => $tenant->id,
                'number' => sprintf('WO-%d-%05d', $raisedAt->year, ++$number),
                'site_id' => $site->id,
                'location_id' => $site->locations->random()->id,
                'category_id' => ($categories->firstWhere('name', self::TITLES[$title]) ?? $categories->random())->id,
                'raised_by' => $people['supervisor']->id,
                'priority' => $priority,
                'status' => $status,
                'source' => [WorkOrderSource::Web, WorkOrderSource::Api, WorkOrderSource::Qr][mt_rand(0, 2)],
                'response_due_at' => $raisedAt->addMinutes($policy->response_minutes),
                'resolution_due_at' => $raisedAt->addMinutes($policy->resolution_minutes),
                'created_at' => $raisedAt,
                'updated_at' => $raisedAt,
            ]);

            $path = match ($status) {
                WorkOrderStatus::Open => [],
                WorkOrderStatus::Cancelled => [WorkOrderStatus::Cancelled],
                WorkOrderStatus::Assigned => [WorkOrderStatus::Assigned],
                WorkOrderStatus::InProgress => [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress],
                WorkOrderStatus::OnHold => [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress, WorkOrderStatus::OnHold],
                WorkOrderStatus::Resolved => [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress, WorkOrderStatus::Resolved],
                WorkOrderStatus::Closed => [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress, WorkOrderStatus::Resolved, WorkOrderStatus::Closed],
            };

            if ($path !== [] && $status !== WorkOrderStatus::Cancelled) {
                $wo->forceFill(['assigned_to' => $technician->id, 'assigned_by' => $people['supervisor']->id]);
            }

            $at = $raisedAt;
            $histories = [[null, WorkOrderStatus::Open, $raisedAt, null]];
            $from = WorkOrderStatus::Open;
            foreach ($path as $step) {
                $at = $at->addMinutes(match ($step) {
                    WorkOrderStatus::Assigned => mt_rand(1, max(2, (int) ($policy->response_minutes * 0.3))),
                    WorkOrderStatus::InProgress => (int) ($policy->response_minutes * ($late ? 1.5 : 0.4)),
                    WorkOrderStatus::Resolved => (int) ($policy->resolution_minutes * ($late ? 1.3 : 0.6)),
                    default => mt_rand(5, 60),
                });
                $at = $at->isFuture() ? CarbonImmutable::now()->subMinute() : $at;
                $note = match ($step) {
                    WorkOrderStatus::Resolved => 'Fixed and tested.',
                    WorkOrderStatus::Cancelled => 'Raised twice.',
                    default => null,
                };
                $histories[] = [$from, $step, $at, $note];
                $from = $step;

                match ($step) {
                    WorkOrderStatus::InProgress => $wo->forceFill(['responded_at' => $at]),
                    WorkOrderStatus::Resolved => $wo->forceFill(['resolved_at' => $at, 'resolution_note' => $note]),
                    WorkOrderStatus::Closed => $wo->forceFill(['closed_at' => $at]),
                    default => null,
                };
            }

            $wo->forceFill([
                'is_response_breached' => $wo->responded_at !== null ? $wo->responded_at->greaterThan($wo->response_due_at) : ($status !== WorkOrderStatus::Cancelled && now()->greaterThan($wo->response_due_at)),
                'is_resolution_breached' => $wo->resolved_at !== null ? $wo->resolved_at->greaterThan($wo->resolution_due_at) : (! $status->isFinished() && $status !== WorkOrderStatus::OnHold && now()->greaterThan($wo->resolution_due_at)),
                'updated_at' => $at,
            ]);
            $wo->saveQuietly();

            foreach ($histories as [$fromStatus, $toStatus, $when, $note]) {
                DB::table('work_order_status_histories')->insert([
                    'tenant_id' => $tenant->id, 'work_order_id' => $wo->id, 'from_status' => $fromStatus?->value,
                    'to_status' => $toStatus->value, 'changed_by' => $people['supervisor']->id, 'note' => $note, 'created_at' => $when,
                ]);
            }
        }

        DB::table('work_order_sequences')->updateOrInsert(['tenant_id' => $tenant->id, 'year' => now()->year], ['last_number' => $number]);
    }

    /**
     * Bulk insert in chunks: no models, events or histories, just rows that
     * look like a busy tenant's last two years.
     */
    private function largeVolume(Tenant $tenant, int $count): void
    {
        $context = app(TenantContext::class);
        $context->set($tenant);

        $siteLocations = Location::query()->get(['id', 'site_id'])->groupBy('site_id')->map->pluck('id')->all();
        $siteIds = array_keys($siteLocations);
        $categoryIds = Category::query()->pluck('id', 'name')->all();
        $technicianIds = User::query()->role(UserRole::Technician->value)->pluck('id')->all();
        $raisedBy = User::query()->role(UserRole::Supervisor->value)->value('id');
        $statuses = array_map(fn (WorkOrderStatus $s) => $s->value, WorkOrderStatus::cases());
        $priorities = array_map(fn (Priority $p) => $p->value, Priority::cases());
        $next = (int) DB::table('work_order_sequences')->where('tenant_id', $tenant->id)->max('last_number');
        $now = CarbonImmutable::now();

        $this->command->getOutput()->progressStart($count);

        foreach (array_chunk(range(1, $count), 1000) as $chunk) {
            $rows = [];
            foreach ($chunk as $_) {
                $siteId = $siteIds[array_rand($siteIds)];
                $status = $statuses[mt_rand(0, 9) < 7 ? 5 : mt_rand(0, 6)]; // mostly closed, like a real history
                $raised = $now->subMinutes(mt_rand(60, 60 * 24 * 730));
                $finished = in_array($status, WorkOrderStatus::finishedValues(), true);
                $assigned = $status !== 'open' && $status !== 'cancelled';
                $resolved = in_array($status, ['resolved', 'closed'], true) ? $raised->addHours(mt_rand(1, 72)) : null;

                $rows[] = [
                    'tenant_id' => $tenant->id,
                    'number' => sprintf('WO-%d-%06d', $raised->year, ++$next),
                    'site_id' => $siteId,
                    'location_id' => $siteLocations[$siteId][array_rand($siteLocations[$siteId]->all())],
                    'category_id' => $categoryIds[self::TITLES[$title = array_rand(self::TITLES)]] ?? reset($categoryIds),
                    'raised_by' => $raisedBy,
                    'assigned_to' => $assigned ? $technicianIds[array_rand($technicianIds)] : null,
                    'title' => $title,
                    'priority' => $priorities[mt_rand(0, 3)],
                    'status' => $status,
                    'source' => 'web',
                    'response_due_at' => $raised->addHours(4),
                    'resolution_due_at' => $raised->addDay(),
                    'responded_at' => $assigned && $status !== 'assigned' ? $raised->addHours(mt_rand(1, 6)) : null,
                    'resolved_at' => $resolved,
                    'closed_at' => $status === 'closed' ? $resolved?->addHours(2) : null,
                    'is_response_breached' => mt_rand(1, 100) <= 10,
                    'is_resolution_breached' => $finished ? ($resolved !== null && $resolved->greaterThan($raised->addDay())) : $raised->addDay()->isPast(),
                    'created_at' => $raised,
                    'updated_at' => $resolved ?? $raised,
                ];
            }

            DB::table('work_orders')->insert($rows);
            $this->command->getOutput()->progressAdvance(count($chunk));
        }

        $this->command->getOutput()->progressFinish();
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
