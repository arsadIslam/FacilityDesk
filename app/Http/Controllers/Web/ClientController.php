<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Clients\DeleteClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ClientController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Client::class);

        return view('clients.index', [
            'clients' => Client::query()->withCount('sites')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.form', ['client' => new Client]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        return redirect()->route('clients.index')->with('status', "{$client->name} was created.");
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.form', ['client' => $client]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('clients.index')->with('status', "{$client->name} was updated.");
    }

    public function destroy(Client $client, DeleteClient $deleteClient): RedirectResponse
    {
        Gate::authorize('delete', $client);

        $deleteClient->handle($client);

        return redirect()->route('clients.index')->with('status', "{$client->name} was deleted.");
    }
}
