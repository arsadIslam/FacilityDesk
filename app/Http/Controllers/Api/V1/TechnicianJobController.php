<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Technician')]
#[Authenticated]
final class TechnicianJobController extends Controller
{
    /**
     * My jobs
     *
     * The caller's unfinished jobs, soonest resolution deadline first.
     * Cursor-paginated: follow `links.next` / `meta.next_cursor`, which stays
     * stable while jobs are added or finished in the meantime.
     */
    #[ResponseFromApiResource(WorkOrderResource::class, WorkOrder::class, collection: true, cursorPaginate: 20, with: ['site', 'location', 'category', 'asset'])]
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasUserRole(UserRole::Technician), 403, 'Only technicians have a job list.');

        $jobs = WorkOrder::query()
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', WorkOrderStatus::finishedValues())
            ->with(['site', 'location', 'category', 'asset'])
            ->orderBy('resolution_due_at')
            ->orderBy('id')
            ->cursorPaginate(20);

        return WorkOrderResource::collection($jobs);
    }
}
