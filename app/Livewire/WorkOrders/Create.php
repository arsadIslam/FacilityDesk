<?php

declare(strict_types=1);

namespace App\Livewire\WorkOrders;

use App\Actions\Attachments\StoreAttachment;
use App\Actions\WorkOrders\CreateWorkOrder;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\WorkOrderSource;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\AttachmentRules;
use App\Support\WorkOrderRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Properties are snake_case and strings because they are bound to form
 * inputs and validated with the same rule keys the API will use.
 */
#[Title('Raise a work order')]
final class Create extends Component
{
    use WithFileUploads;

    public string $site_id = '';

    public string $location_id = '';

    public string $category_id = '';

    public string $priority = '';

    public string $title = '';

    public string $description = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $photos = [];

    /** Set when opened from an asset's QR scan page; validated against the site on save. */
    public string $asset_id = '';

    #[Locked]
    public bool $fromQr = false;

    public function mount(): void
    {
        Gate::authorize('create', WorkOrder::class);

        $token = request()->query('asset');

        if (is_string($token) && $token !== '') {
            $this->prefillFromAsset($token);

            return;
        }

        // Most client users have a single site; preselect it.
        $sites = $this->sites();

        if ($sites->count() === 1) {
            $this->site_id = (string) $sites->keys()->first();
        }
    }

    /**
     * Drop the location only if it is not in the newly chosen site. Livewire
     * may apply site_id and location_id in the same request, so clearing
     * unconditionally would discard a valid choice.
     */
    public function updatedSiteId(): void
    {
        if (! $this->locations()->has((int) $this->location_id)) {
            $this->location_id = '';
        }

        if ($this->asset() !== null && $this->asset()->site_id !== (int) $this->site_id) {
            $this->clearAsset();
        }
    }

    public function clearAsset(): void
    {
        $this->asset_id = '';
        $this->fromQr = false;
    }

    /**
     * The scanned asset, if any. Tenant-scoped, so another tenant's id is null.
     */
    #[Computed]
    public function asset(): ?Asset
    {
        return ctype_digit($this->asset_id) ? Asset::query()->with('location')->find((int) $this->asset_id) : null;
    }

    /**
     * Suggest the category's default priority until the user picks one.
     */
    public function updatedCategoryId(): void
    {
        if ($this->priority === '' && ctype_digit($this->category_id)) {
            $this->priority = Category::query()->find((int) $this->category_id)?->default_priority->value ?? '';
        }
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

    public function save(CreateWorkOrder $createWorkOrder, StoreAttachment $storeAttachment): void
    {
        $user = $this->user();
        $siteId = ctype_digit($this->site_id) ? (int) $this->site_id : null;

        $this->validate([
            ...WorkOrderRules::create($user, $siteId),
            'photos' => ['array', 'max:'.AttachmentRules::MAX_PER_WORK_ORDER_CREATE],
            'photos.*' => AttachmentRules::file(),
        ], attributes: [
            'site_id' => 'site',
            'location_id' => 'location',
            'category_id' => 'category',
            'photos.*' => 'photo',
            'asset_id' => 'asset',
        ]);

        $site = Site::query()->findOrFail($siteId);
        $description = trim($this->description);

        $workOrder = $createWorkOrder->handle(
            raisedBy: $user,
            site: $site,
            location: $site->locations()->findOrFail((int) $this->location_id),
            category: Category::query()->findOrFail((int) $this->category_id),
            title: trim($this->title),
            description: $description === '' ? null : $description,
            priority: WorkOrderRules::canChoosePriority($user) ? Priority::tryFrom($this->priority) : null,
            source: $this->fromQr ? WorkOrderSource::Qr : WorkOrderSource::Web,
            asset: ctype_digit($this->asset_id) ? Asset::query()->findOrFail((int) $this->asset_id) : null,
        );

        // Photos are stored after the work order commits: a failed upload must
        // never lose the request itself.
        foreach ($this->photos as $photo) {
            $storeAttachment->handle($workOrder, $photo, $user);
        }

        session()->flash('status', "{$workOrder->number} was raised.");

        $this->redirectRoute('work-orders.show', $workOrder);
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function sites(): Collection
    {
        $query = Site::query()->where('is_active', true)->orderBy('name');

        if ($this->user()->hasUserRole(UserRole::ClientUser)) {
            $query->where('client_id', $this->user()->client_id ?? 0);
        }

        return $query->pluck('name', 'id');
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function locations(): Collection
    {
        if (! ctype_digit($this->site_id)) {
            return collect();
        }

        return Location::query()->where('site_id', (int) $this->site_id)->orderBy('path')->pluck('path', 'id');
    }

    /**
     * @return Collection<int, string>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id');
    }

    public function render(): View
    {
        return view('livewire.work-orders.create', [
            'priorities' => Priority::cases(),
            'canChoosePriority' => WorkOrderRules::canChoosePriority($this->user()),
        ]);
    }

    private function prefillFromAsset(string $token): void
    {
        $asset = Asset::query()->where('qr_token', $token)->firstOrFail();
        Gate::authorize('view', $asset);

        $this->asset_id = (string) $asset->id;
        $this->site_id = (string) $asset->site_id;
        $this->location_id = (string) $asset->location_id;
        $this->category_id = (string) $asset->category_id;
        $this->fromQr = true;
        $this->updatedCategoryId();
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
