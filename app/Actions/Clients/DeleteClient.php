<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Validation\ValidationException;

final class DeleteClient
{
    /**
     * @throws ValidationException
     */
    public function handle(Client $client): void
    {
        if ($client->sites()->exists()) {
            throw ValidationException::withMessages([
                'client' => "{$client->name} still has sites. Delete or move them first.",
            ]);
        }

        $client->delete();
    }
}
