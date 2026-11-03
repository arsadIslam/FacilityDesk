<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a node in a site's location tree and keeps the cached
 * "Block A / 3rd Floor / Room 304" path of the node and its descendants fresh.
 */
final class SaveLocation
{
    public const SEPARATOR = ' / ';

    /**
     * @throws ValidationException
     */
    public function handle(Site $site, LocationType $type, string $name, ?int $parentId, ?Location $location = null): Location
    {
        $location ??= new Location;

        $parent = $parentId === null
            ? null
            : $site->locations()->findOrFail($parentId);

        if ($parent !== null && $location->exists && $this->isSelfOrDescendant($location, $parent)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A location cannot be moved under itself or one of its children.',
            ]);
        }

        return DB::transaction(function () use ($site, $type, $name, $parent, $location): Location {
            $location->fill(['type' => $type, 'name' => $name]);
            $location->site_id = $site->id;
            $location->parent_id = $parent?->id;
            $location->path = $parent === null ? $name : $parent->path.self::SEPARATOR.$name;

            $pathChanged = $location->isDirty('path');
            $location->save();

            if ($pathChanged) {
                $this->refreshDescendantPaths($location);
            }

            return $location;
        });
    }

    private function isSelfOrDescendant(Location $location, Location $candidate): bool
    {
        $ids = [$location->id];
        $frontier = [$location->id];

        while ($frontier !== []) {
            $frontier = Location::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return in_array($candidate->id, $ids, true);
    }

    /**
     * Breadth-first: one query per tree level, not per node.
     */
    private function refreshDescendantPaths(Location $root): void
    {
        $level = collect([$root]);

        while ($level->isNotEmpty()) {
            $paths = $level->pluck('path', 'id');

            $level = Location::query()->whereIn('parent_id', $paths->keys())->get()
                ->each(function (Location $child) use ($paths): void {
                    $child->path = $paths[$child->parent_id].self::SEPARATOR.$child->name;
                    $child->save();
                });
        }
    }
}
