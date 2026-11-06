@props(['site', 'tree', 'parentId' => 0, 'canManage' => false])

{{-- $tree: locations grouped by parent_id (0 = root), built in SiteController. --}}
<ul @class(['space-y-1', 'ml-5 border-l border-slate-200 pl-4' => $parentId !== 0])>
    @foreach ($tree->get($parentId, []) as $location)
        <li>
            <div class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                <div>
                    <span class="font-medium text-slate-900">{{ $location->name }}</span>
                    <span class="ml-2 text-xs uppercase tracking-wide text-slate-500">{{ $location->type->label() }}</span>
                </div>
                @if ($canManage)
                    <div class="flex gap-3 text-xs">
                        <a href="{{ route('sites.locations.edit', [$site, $location]) }}" class="font-medium text-indigo-600 hover:text-indigo-500">Edit</a>
                        <form method="POST" action="{{ route('sites.locations.destroy', [$site, $location]) }}" data-confirm="Delete {{ $location->name }}?">
                            @csrf @method('DELETE')
                            <button class="font-medium text-red-600 hover:text-red-500">Delete</button>
                        </form>
                    </div>
                @endif
            </div>
            @if ($tree->has($location->id))
                <x-location-tree :site="$site" :tree="$tree" :parent-id="$location->id" :can-manage="$canManage" />
            @endif
        </li>
    @endforeach
</ul>
