<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Clients\CreateClient;
use App\Application\Actions\Clients\DeleteClient;
use App\Application\Actions\Clients\UpdateClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Client::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) ($request->input('search') ?? $request->input('filter.search', '')));
        $status = $request->input('status') ?? $request->input('filter.status');

        $clients = Client::query()
            ->where('workspace_id', $workspace->id)
            ->with('primaryContact')
            ->withCount('contacts')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderBy('name')
            ->get();

        return ClientResource::collection($clients);
    }

    public function store(
        StoreClientRequest $request,
        CreateClient $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        $client = $action->execute($workspace->id, $request->user()->id, $request->validated());

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'client.created',
            Client::class,
            $client->id,
            null,
            $client->toArray()
        );

        return (new ClientResource($client->load('primaryContact')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client)
    {
        $workspace = app('currentWorkspace');

        abort_unless($client->workspace_id === $workspace->id, 404);

        $this->authorize('view', $client);

        return new ClientResource($client->load([
            'contacts.client',
            'primaryContact',
        ])->loadCount('contacts'));
    }

    public function update(
        UpdateClientRequest $request,
        Client $client,
        UpdateClient $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($client->workspace_id === $workspace->id, 404);

        $before = $client->toArray();

        $client = $action->execute($client, $request->user()->id, $request->validated())->load('primaryContact');

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'client.updated',
            Client::class,
            $client->id,
            $before,
            $client->toArray()
        );

        return new ClientResource($client);
    }

    public function destroy(
        Request $request,
        Client $client,
        DeleteClient $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($client->workspace_id === $workspace->id, 404);

        $this->authorize('delete', $client);

        $result = $action->execute($client);
        if (!$result['deleted']) {
            return response()->json([
                'message' => 'This client cannot be deleted while related contacts or events still exist.',
                'data' => $result['dependencies'],
            ], 409);
        }

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'client.deleted',
            Client::class,
            $client->id,
            $result['before'],
            null
        );

        return response()->noContent();
    }
}
