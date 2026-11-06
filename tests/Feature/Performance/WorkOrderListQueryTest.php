<?php

use App\Models\WorkOrder;
use App\Queries\WorkOrderListQuery;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->w = workOrderWorld();
    $this->a = WorkOrder::factory()->forSite($this->w->site)->create(['number' => 'WO-2026-00123', 'title' => 'Water leakage in pantry']);
    $this->b = WorkOrder::factory()->forSite($this->w->site)->create(['number' => 'WO-2026-00456', 'title' => 'Lights flickering']);
});

function searchIds(stdClass $w, string $search): array
{
    return app(WorkOrderListQuery::class)->build($w->supervisor, ['search' => $search])->pluck('id')->all();
}

it('finds a full number exactly, case-insensitively', function () {
    expect(searchIds($this->w, 'wo-2026-00123'))->toBe([$this->a->id]);
});

it('matches digits against the number only', function () {
    expect(searchIds($this->w, '456'))->toBe([$this->b->id]);
});

it('matches words against the title', function () {
    if (DB::getDriverName() === 'mysql') {
        // InnoDB FULLTEXT only indexes committed rows and every test runs in a
        // rolled-back transaction, so on MySQL check the query instead.
        $sql = app(WorkOrderListQuery::class)->build($this->w->supervisor, ['search' => 'leak +x* pantry'])->toRawSql();

        expect(strtolower($sql))->toContain("match (`title`) against ('+leak* +pantry*' in boolean mode)");

        return;
    }

    expect(searchIds($this->w, 'leak'))->toBe([$this->a->id])
        ->and(searchIds($this->w, 'FLICKER'))->toBe([$this->b->id]);
});

it('treats LIKE wildcards in the search as literal text', function () {
    expect(searchIds($this->w, '%'))->toBe([])
        ->and(searchIds($this->w, '_'))->toBe([]);
});

it('ignores malformed filters instead of passing them to the database', function () {
    $ids = app(WorkOrderListQuery::class)->build($this->w->supervisor, [
        'status' => 'nonsense', 'priority' => 'urgent', 'site_id' => '1 OR 1=1', 'from' => 'yesterday',
    ])->pluck('id')->all();

    expect($ids)->toHaveCount(2);
});

it('loads every relation the list shows without N+1 queries', function () {
    $queriesFor = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        app(WorkOrderListQuery::class)->build($this->w->supervisor)->paginate(20)
            ->each(fn (WorkOrder $wo) => [$wo->site->name, $wo->location->path, $wo->category->name, $wo->assignee?->name, $wo->raisedBy->name]);

        return count(DB::getQueryLog());
    };

    WorkOrder::query()->update(['assigned_to' => $this->w->technician->id]);
    $queriesFor(); // warm-up: the permission cache loads once per request

    $withTwo = $queriesFor();
    WorkOrder::factory()->forSite($this->w->site)->assignedTo($this->w->technician)->count(18)->create();

    // Same number of queries for 2 rows and 20 rows: nothing is loaded per row.
    expect($queriesFor())->toBe($withTwo);
});
