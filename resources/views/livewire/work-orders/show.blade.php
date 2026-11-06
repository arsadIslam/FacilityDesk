@php $tz = $workOrder->site->timezone; @endphp

<div>
    <div class="mb-6">
        <a href="{{ route('work-orders.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">← Work orders</a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-slate-900">{{ $workOrder->title }}</h1>
            <x-work-orders.status-badge :status="$workOrder->status" />
            <x-work-orders.priority-badge :priority="$workOrder->priority" />
        </div>
        <p class="mt-1 font-mono text-sm text-slate-500">{{ $workOrder->number }}</p>
    </div>

    @if ($flash)
        <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ $flash }}</div>
    @endif
    @error('status') <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div> @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="p-6">
                <h2 class="text-base font-semibold text-slate-900">Details</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $workOrder->description ?? 'No description.' }}</p>

                @if ($workOrder->resolution_note)
                    <div class="mt-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">
                        <p class="font-medium">Resolution</p>
                        <p class="mt-1 whitespace-pre-line">{{ $workOrder->resolution_note }}</p>
                    </div>
                @endif
            </x-card>

            <x-card class="p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-900">Photos &amp; files</h2>
                    <span class="text-xs text-slate-500">{{ $workOrder->attachments->count() }}</span>
                </div>

                @if ($workOrder->attachments->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">No photos yet.</p>
                @else
                    <ul class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach ($workOrder->attachments as $attachment)
                            <li wire:key="attachment-{{ $attachment->id }}" class="text-xs">
                                <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-md ring-1 ring-slate-200 hover:ring-indigo-400">
                                    @if ($attachment->isImage())
                                        <img src="{{ $attachment->url(thumbnail: true) }}" alt="{{ $attachment->kind->label() }} photo" loading="lazy" class="aspect-square w-full object-cover">
                                    @else
                                        <span class="flex aspect-square w-full items-center justify-center bg-slate-50 font-semibold text-slate-600">PDF</span>
                                    @endif
                                </a>
                                <p class="mt-1 font-medium text-slate-700">{{ $attachment->kind->label() }}</p>
                                <p class="truncate text-slate-500" title="{{ $attachment->original_name }}">{{ $attachment->uploader->name }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($canUpload)
                    <form wire:submit="uploadPhoto" class="mt-6 flex flex-wrap items-end gap-3 border-t border-slate-200 pt-4">
                        <div>
                            <label for="photo_kind" class="block text-sm font-medium text-slate-700">Type</label>
                            <select id="photo_kind" wire:model="photo_kind" class="mt-1 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="before">Before</option>
                                <option value="after">After</option>
                            </select>
                        </div>
                        <div class="min-w-0 flex-1">
                            <label for="photo" class="block text-sm font-medium text-slate-700">Photo</label>
                            <input id="photo" type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp,application/pdf"
                                   class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                        </div>
                        <x-button wire:loading.attr="disabled" wire:target="photo,uploadPhoto">Upload</x-button>
                        <div class="w-full">
                            @error('photo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('photo_kind') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </form>
                @endif
            </x-card>

            <x-card class="p-6">
                <h2 class="text-base font-semibold text-slate-900">Comments</h2>

                <ul class="mt-4 space-y-4">
                    @forelse ($this->comments as $comment)
                        <li wire:key="comment-{{ $comment->id }}" @class(['rounded-md p-3 text-sm', 'bg-amber-50 ring-1 ring-amber-200' => $comment->is_internal, 'bg-slate-50' => ! $comment->is_internal])>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="font-medium text-slate-800">{{ $comment->user->name }}</span>
                                <x-datetime :value="$comment->created_at" :timezone="$tz" />
                                @if ($comment->is_internal)
                                    <span class="rounded bg-amber-200 px-1.5 py-0.5 font-semibold uppercase text-amber-900">Internal</span>
                                @endif
                            </div>
                            <p class="mt-1 whitespace-pre-line text-slate-800">{{ $comment->body }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No comments yet.</li>
                    @endforelse
                </ul>

                @if ($canComment)
                    <form wire:submit="addComment" class="mt-6 space-y-3 border-t border-slate-200 pt-4">
                        <label for="comment_body" class="sr-only">Add a comment</label>
                        <textarea id="comment_body" wire:model="comment_body" rows="3" maxlength="5000" placeholder="Add a comment…"
                                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('comment_body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="flex items-center justify-between">
                            @if ($canCommentInternally)
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="comment_internal" class="h-4 w-4 rounded border-slate-300 text-amber-600">
                                    Internal (hidden from the client)
                                </label>
                            @else
                                <span></span>
                            @endif
                            <x-button>Post comment</x-button>
                        </div>
                    </form>
                @endif
            </x-card>

            <x-card class="p-6">
                <h2 class="mb-4 text-base font-semibold text-slate-900">Timeline</h2>
                <x-work-orders.timeline :work-order="$workOrder" />
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($this->technicians->isNotEmpty() || $this->transitions !== [])
                <x-card class="p-6">
                    <h2 class="text-base font-semibold text-slate-900">Actions</h2>

                    <div class="mt-3">
                        <label for="note" class="block text-sm font-medium text-slate-700">Note</label>
                        <textarea id="note" wire:model="note" rows="2" maxlength="500"
                                  placeholder="Required to resolve, reopen or cancel"
                                  class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                        @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($this->technicians->isNotEmpty())
                        <form wire:submit="assign" class="mt-4 space-y-2">
                            <label for="assignee_id" class="block text-sm font-medium text-slate-700">
                                {{ $workOrder->assigned_to ? 'Reassign to' : 'Assign to' }}
                            </label>
                            <select id="assignee_id" wire:model="assignee_id" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                                <option value="">Choose a technician</option>
                                @foreach ($this->technicians as $technician)
                                    <option value="{{ $technician->id }}" @disabled($technician->id === $workOrder->assigned_to)>
                                        {{ $technician->name }}
                                        ({{ collect([$technician->covers_site ? 'covers site' : null, $technician->has_skill ? 'skilled' : null, $technician->open_jobs.' open'])->filter()->implode(', ') }})
                                    </option>
                                @endforeach
                            </select>
                            @error('assignee_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <x-button class="w-full">{{ $workOrder->assigned_to ? 'Reassign' : 'Assign' }}</x-button>
                        </form>
                    @endif

                    @if ($this->transitions !== [])
                        <div class="mt-4 grid gap-2">
                            @foreach ($this->transitions as $to)
                                <x-button type="button" wire:click="transition('{{ $to->value }}')"
                                          :variant="$to === App\Enums\WorkOrderStatus::Cancelled ? 'danger' : 'secondary'">
                                    {{ $workOrder->status->actionLabel($to) }}
                                </x-button>
                            @endforeach
                        </div>
                    @endif
                </x-card>
            @endif

            <x-card class="p-6 text-sm">
                <h2 class="text-base font-semibold text-slate-900">Information</h2>
                <dl class="mt-3 space-y-2">
                    <div><dt class="text-slate-500">Site</dt><dd>{{ $workOrder->site->name }} · {{ $workOrder->site->client->name }}</dd></div>
                    <div><dt class="text-slate-500">Location</dt><dd>{{ $workOrder->location->path }}</dd></div>
                    <div><dt class="text-slate-500">Category</dt><dd>{{ $workOrder->category->name }}</dd></div>
                    @if ($workOrder->asset)
                        <div><dt class="text-slate-500">Asset</dt><dd><a href="{{ route('assets.show', $workOrder->asset) }}" class="text-indigo-600 hover:text-indigo-500">{{ $workOrder->asset->code }}</a> · {{ $workOrder->asset->name }}</dd></div>
                    @endif
                    <div><dt class="text-slate-500">Raised by</dt><dd>{{ $workOrder->raisedBy->name }} · <x-datetime :value="$workOrder->created_at" :timezone="$tz" /></dd></div>
                    <div><dt class="text-slate-500">Assignee</dt><dd>{{ $workOrder->assignee?->name ?? 'Unassigned' }}</dd></div>
                </dl>
            </x-card>

            <x-card class="p-6 text-sm">
                <h2 class="text-base font-semibold text-slate-900">SLA <span class="font-normal text-slate-500">({{ $tz }})</span></h2>
                <dl class="mt-3 space-y-2">
                    <div>
                        <dt class="text-slate-500">Respond by</dt>
                        <dd><x-datetime :value="$workOrder->response_due_at" :timezone="$tz" />
                            @if ($workOrder->responded_at) <span class="text-slate-500">· responded <x-datetime :value="$workOrder->responded_at" :timezone="$tz" /></span> @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Resolve by</dt>
                        <dd><x-work-orders.due :work-order="$workOrder" />
                            @if ($workOrder->hold_minutes > 0) <span class="text-slate-500">· extended {{ $workOrder->hold_minutes }} min on hold</span> @endif
                        </dd>
                    </div>
                    @if ($workOrder->resolved_at)
                        <div><dt class="text-slate-500">Resolved</dt><dd><x-datetime :value="$workOrder->resolved_at" :timezone="$tz" /></dd></div>
                    @endif
                    @if ($workOrder->closed_at)
                        <div><dt class="text-slate-500">Closed</dt><dd><x-datetime :value="$workOrder->closed_at" :timezone="$tz" /></dd></div>
                    @endif
                </dl>
            </x-card>
        </div>
    </div>
</div>
