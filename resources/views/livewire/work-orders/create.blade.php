<div>
    <x-page-header title="Raise a work order" subtitle="Describe the problem and where it is. The SLA clock starts when you submit." />

    <x-card class="max-w-2xl p-6">
        <form wire:submit="save" class="space-y-5">
            @if ($this->asset)
                <div class="flex items-start justify-between gap-3 rounded-md bg-indigo-50 px-4 py-3 text-sm text-indigo-900 ring-1 ring-indigo-200">
                    <div>
                        <p class="font-medium">{{ $this->asset->code }} · {{ $this->asset->name }}</p>
                        <p class="text-indigo-700">{{ $this->asset->location->path }}</p>
                    </div>
                    <button type="button" wire:click="clearAsset" class="text-xs font-medium text-indigo-700 hover:text-indigo-900">Not this asset</button>
                </div>
                @error('asset_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="site_id" class="block text-sm font-medium text-slate-700">Site</label>
                    <select id="site_id" wire:model.live="site_id" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Choose a site</option>
                        @foreach ($this->sites as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('site_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="location_id" class="block text-sm font-medium text-slate-700">Location</label>
                    <select id="location_id" wire:model="location_id" @disabled($this->locations->isEmpty())
                            class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-50">
                        <option value="">{{ $site_id === '' ? 'Choose a site first' : ($this->locations->isEmpty() ? 'This site has no locations yet' : 'Choose a location') }}</option>
                        @foreach ($this->locations as $id => $path)
                            <option value="{{ $id }}">{{ $path }}</option>
                        @endforeach
                    </select>
                    @error('location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="category_id" class="block text-sm font-medium text-slate-700">Category</label>
                    <select id="category_id" wire:model.live="category_id" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Choose a category</option>
                        @foreach ($this->categories as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($canChoosePriority)
                    <div>
                        <label for="priority" class="block text-sm font-medium text-slate-700">Priority</label>
                        <select id="priority" wire:model="priority" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">Category default</option>
                            @foreach ($priorities as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-slate-700">Title</label>
                <input id="title" type="text" wire:model="title" maxlength="150" placeholder="e.g. AC not cooling in meeting room"
                       class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700">Details <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="description" wire:model="description" rows="4" maxlength="5000"
                          class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="photos" class="block text-sm font-medium text-slate-700">
                    Photos <span class="font-normal text-slate-500">(optional, up to {{ App\Support\AttachmentRules::MAX_PER_WORK_ORDER_CREATE }}; JPG, PNG, WebP or PDF, 5 MB each)</span>
                </label>
                <input id="photos" type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                <div wire:loading wire:target="photos" class="mt-1 text-sm text-slate-500">Uploading…</div>
                @error('photos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @foreach ($errors->get('photos.*') as $messages)
                    <p class="mt-1 text-sm text-red-600">{{ $messages[0] }}</p>
                @endforeach

                @if ($photos)
                    <ul class="mt-3 flex flex-wrap gap-3">
                        @foreach ($photos as $index => $photo)
                            <li wire:key="photo-{{ $index }}" class="relative">
                                @if ($photo->isPreviewable())
                                    <img src="{{ $photo->temporaryUrl() }}" alt="" class="h-20 w-20 rounded-md object-cover ring-1 ring-slate-200">
                                @else
                                    <span class="flex h-20 w-20 items-center justify-center rounded-md bg-slate-100 text-xs text-slate-600 ring-1 ring-slate-200">PDF</span>
                                @endif
                                <button type="button" wire:click="removePhoto({{ $index }})" aria-label="Remove photo"
                                        class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 text-xs text-white">×</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="flex gap-3">
                <x-button wire:loading.attr="disabled">Raise work order</x-button>
                <x-button variant="secondary" :href="route('work-orders.index')" wire:navigate>Cancel</x-button>
            </div>
        </form>
    </x-card>
</div>
