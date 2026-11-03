<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Models\Location;
use Illuminate\Validation\ValidationException;

final class DeleteLocation
{
    /**
     * @throws ValidationException
     */
    public function handle(Location $location): void
    {
        if ($location->children()->exists()) {
            throw ValidationException::withMessages([
                'location' => "Remove the locations inside {$location->name} first.",
            ]);
        }

        $location->delete();
    }
}
