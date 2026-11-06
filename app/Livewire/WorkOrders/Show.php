<?php

declare(strict_types=1);

namespace App\Livewire\WorkOrders;

use App\Actions\Attachments\StoreAttachment;
use App\Actions\WorkOrders\AddComment;
use App\Actions\WorkOrders\AssignWorkOrder;
use App\Actions\WorkOrders\ChangeStatus;
use App\Enums\AttachmentKind;
use App\Enums\UserRole;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Support\AttachmentRules;
use App\Support\TenantRules;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Work order detail: info, actions, status timeline and comments.
 *
 * $workOrder is a model property, so Livewire re-fetches it by id on every
 * request (tenant-scoped, see the livewire.update route) and the client
 * cannot swap it for another record.
 */
final class Show extends Component
{
    use WithFileUploads;

    public WorkOrder $workOrder;

    public string $assignee_id = '';

    public string $note = '';

    public string $comment_body = '';

    public bool $comment_internal = false;

    public ?string $flash = null;

    public ?TemporaryUploadedFile $photo = null;

    public string $photo_kind = 'before';

    public function mount(WorkOrder $workOrder): void
    {
        Gate::authorize('view', $workOrder);

        $this->workOrder = $workOrder;
    }

    public function assign(AssignWorkOrder $assignWorkOrder): void
    {
        $this->validate(
            ['assignee_id' => ['required', 'integer', TenantRules::exists('users')->withoutTrashed()]],
            attributes: ['assignee_id' => 'technician'],
        );

        $assignee = User::query()->findOrFail((int) $this->assignee_id);
        $this->workOrder = $assignWorkOrder->handle($this->workOrder, $assignee, $this->user(), $this->note);

        $this->done("Assigned to {$assignee->name}.");
    }

    public function transition(string $status, ChangeStatus $changeStatus): void
    {
        $to = WorkOrderStatus::tryFrom($status);
        abort_if($to === null, 422);

        $this->validate(['note' => ['nullable', 'string', 'max:500']]);

        $this->workOrder = $changeStatus->handle($this->workOrder, $to, $this->user(), $this->note);

        $this->done("Moved to {$to->label()}.");
    }

    public function addComment(AddComment $addComment): void
    {
        $this->validate(['comment_body' => ['required', 'string', 'max:5000']], attributes: ['comment_body' => 'comment']);

        $addComment->handle($this->workOrder, $this->user(), trim($this->comment_body), $this->comment_internal);

        $this->reset('comment_body', 'comment_internal');
        unset($this->comments);
        $this->flash = 'Comment added.';
    }

    public function uploadPhoto(StoreAttachment $storeAttachment): void
    {
        Gate::authorize('upload', $this->workOrder);

        $this->validate([
            'photo' => ['required', ...AttachmentRules::file()],
            'photo_kind' => ['required', Rule::enum(AttachmentKind::class)->only([AttachmentKind::Before, AttachmentKind::After])],
        ]);

        /** @var TemporaryUploadedFile $photo */
        $photo = $this->photo;
        $storeAttachment->handle($this->workOrder, $photo, $this->user(), AttachmentKind::from($this->photo_kind));

        $this->reset('photo');
        $this->flash = 'Photo uploaded.';
    }

    /**
     * Status changes this user may make right now, excluding assignment
     * (which has its own form because it needs a technician).
     *
     * @return list<WorkOrderStatus>
     */
    #[Computed]
    public function transitions(): array
    {
        $from = $this->workOrder->status;

        return array_values(array_filter(
            $from->allowedTransitions(),
            fn (WorkOrderStatus $to) => ($to !== WorkOrderStatus::Assigned || $from === WorkOrderStatus::Resolved)
                && Gate::allows('transition', [$this->workOrder, $to]),
        ));
    }

    /**
     * Active technicians, best matches first: those covering the site and
     * skilled in the category.
     *
     * @return EloquentCollection<int, User>
     */
    #[Computed]
    public function technicians(): EloquentCollection
    {
        if (! Gate::allows('assign', $this->workOrder)) {
            return new EloquentCollection;
        }

        return User::query()
            ->where('is_active', true)
            ->role(UserRole::Technician->value)
            ->withExists([
                'sites as covers_site' => fn ($q) => $q->whereKey($this->workOrder->site_id),
                'skills as has_skill' => fn ($q) => $q->whereKey($this->workOrder->category_id),
            ])
            ->withCount(['assignedWorkOrders as open_jobs' => fn ($q) => $q->whereNotIn('status', WorkOrderStatus::finishedValues())])
            ->orderByDesc('covers_site')
            ->orderByDesc('has_skill')
            ->orderBy('name')
            ->get();
    }

    /**
     * Internal comments are filtered out in the query for users who may not see them.
     *
     * @return EloquentCollection<int, WorkOrderComment>
     */
    #[Computed]
    public function comments(): EloquentCollection
    {
        return $this->workOrder->comments()
            ->visibleTo($this->user())
            ->with('user')
            ->oldest()
            ->oldest('id')
            ->get();
    }

    public function render(): View
    {
        $this->workOrder->load([
            'site.client', 'location', 'category', 'asset', 'raisedBy', 'assignee', 'assigner',
            'statusHistories' => fn ($q) => $q->with('changedBy')->orderBy('id'),
            'attachments' => fn ($q) => $q->with('uploader')->orderBy('id'),
        ]);

        return view('livewire.work-orders.show', [
            'canComment' => Gate::allows('comment', $this->workOrder),
            'canCommentInternally' => Gate::allows('viewInternalComments', WorkOrder::class),
            'canUpload' => Gate::allows('upload', $this->workOrder),
        ])->title($this->workOrder->number);
    }

    private function done(string $message): void
    {
        $this->reset('assignee_id', 'note');
        $this->resetErrorBag();
        $this->flash = $message;
        unset($this->transitions, $this->technicians);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
