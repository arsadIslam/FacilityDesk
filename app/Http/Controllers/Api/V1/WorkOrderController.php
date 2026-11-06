<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attachments\StoreAttachment;
use App\Actions\WorkOrders\AssignWorkOrder;
use App\Actions\WorkOrders\ChangeStatus;
use App\Actions\WorkOrders\CreateWorkOrder;
use App\Enums\Priority;
use App\Enums\WorkOrderSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignWorkOrderRequest;
use App\Http\Requests\Api\IndexWorkOrdersRequest;
use App\Http\Requests\Api\StoreWorkOrderRequest;
use App\Http\Requests\Api\UpdateStatusRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\TenantContext;
use App\Support\WorkOrderRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Work orders')]
#[Authenticated]
final class WorkOrderController extends Controller
{
    /** @var list<string> */
    private const LIST_RELATIONS = ['site', 'location', 'category', 'asset', 'raisedBy', 'assignee'];

    /**
     * List work orders
     *
     * Only what the caller may see: everything for supervisors and admins,
     * assigned jobs for technicians, their client's work orders for client users.
     */
    #[ResponseFromApiResource(WorkOrderResource::class, WorkOrder::class, collection: true, paginate: 20, with: self::LIST_RELATIONS)]
    public function index(IndexWorkOrdersRequest $request, TenantContext $context): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $timezone = $context->tenant()->timezone ?? 'UTC';

        $workOrders = WorkOrder::query()
            ->visibleTo($user)
            ->with(self::LIST_RELATIONS)
            ->when($request->validated('status'), fn ($q, string $status) => $q->where('status', $status))
            ->when($request->validated('priority'), fn ($q, string $priority) => $q->where('priority', $priority))
            ->when($request->validated('site_id'), fn ($q, $id) => $q->where('site_id', (int) $id))
            ->when($request->validated('assigned_to'), fn ($q, $id) => $q->where('assigned_to', (int) $id))
            ->when($request->validated('from'), fn ($q, string $from) => $q->where('created_at', '>=', Carbon::parse($from, $timezone)->startOfDay()->utc()))
            ->when($request->validated('to'), fn ($q, string $to) => $q->where('created_at', '<=', Carbon::parse($to, $timezone)->endOfDay()->utc()))
            ->latest()
            ->latest('id')
            ->paginate($request->integer('per_page', 20) ?: 20)
            ->withQueryString();

        return WorkOrderResource::collection($workOrders);
    }

    /**
     * Raise a work order
     *
     * Send as `multipart/form-data` to attach photos.
     */
    #[ResponseFromApiResource(WorkOrderResource::class, WorkOrder::class, status: 201, with: self::LIST_RELATIONS)]
    public function store(StoreWorkOrderRequest $request, CreateWorkOrder $createWorkOrder, StoreAttachment $storeAttachment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $site = Site::query()->findOrFail($request->integer('site_id'));

        $workOrder = $createWorkOrder->handle(
            raisedBy: $user,
            site: $site,
            location: $site->locations()->findOrFail($request->integer('location_id')),
            category: Category::query()->findOrFail($request->integer('category_id')),
            title: $request->string('title')->trim()->toString(),
            description: $request->filled('description') ? $request->string('description')->trim()->toString() : null,
            priority: WorkOrderRules::canChoosePriority($user) ? Priority::tryFrom($request->string('priority')->toString()) : null,
            source: WorkOrderSource::Api,
            asset: $request->filled('asset_id') ? Asset::query()->findOrFail($request->integer('asset_id')) : null,
        );

        /** @var list<UploadedFile> $photos */
        $photos = $request->file('photos', []);
        foreach ($photos as $photo) {
            $storeAttachment->handle($workOrder, $photo, $user);
        }

        return (new WorkOrderResource($workOrder->load(self::LIST_RELATIONS)))->response()->setStatusCode(201);
    }

    /**
     * Show a work order
     *
     * Includes the status history, comments (internal ones only for staff),
     * attachments and the actions available to the caller.
     */
    #[ResponseFromApiResource(WorkOrderResource::class, WorkOrder::class, with: [...self::LIST_RELATIONS, 'statusHistories', 'comments', 'attachments'])]
    public function show(Request $request, WorkOrder $workOrder): WorkOrderResource
    {
        Gate::authorize('view', $workOrder);

        return new WorkOrderResource($this->loadDetail($request, $workOrder));
    }

    /**
     * Assign or reassign
     *
     * Supervisors and admins. The work order must be open or assigned.
     */
    #[ResponseFromApiResource(WorkOrderResource::class, WorkOrder::class, with: self::LIST_RELATIONS)]
    public function assign(AssignWorkOrderRequest $request, WorkOrder $workOrder, AssignWorkOrder $assignWorkOrder): WorkOrderResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $assignee = User::query()->findOrFail($request->integer('assignee_id'));

        $workOrder = $assignWorkOrder->handle($workOrder, $assignee, $actor, $request->validated('note'));

        return new WorkOrderResource($workOrder->load(self::LIST_RELATIONS));
    }

    /**
     * Change status
     *
     * Technicians (on their jobs): in_progress, on_hold, resolved. Client users:
     * closed or assigned (reopen) once resolved. Supervisors and admins: any
     * legal move. Illegal moves are 422, moves the caller may not make are 403.
     */
    #[ResponseFromApiResource(WorkOrderResource::class, WorkOrder::class, with: self::LIST_RELATIONS)]
    public function status(UpdateStatusRequest $request, WorkOrder $workOrder, ChangeStatus $changeStatus): WorkOrderResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $workOrder = $changeStatus->handle($workOrder, $request->status(), $actor, $request->validated('note'));

        return new WorkOrderResource($workOrder->load(self::LIST_RELATIONS));
    }

    private function loadDetail(Request $request, WorkOrder $workOrder): WorkOrder
    {
        /** @var User $user */
        $user = $request->user();

        return $workOrder->load([
            ...self::LIST_RELATIONS,
            'statusHistories' => fn ($q) => $q->with('changedBy')->orderBy('id'),
            'comments' => fn ($q) => $q->visibleTo($user)->with('user')->oldest('id'),
            'attachments' => fn ($q) => $q->with('uploader')->orderBy('id'),
        ]);
    }
}
