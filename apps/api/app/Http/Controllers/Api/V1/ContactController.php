<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Contacts\CreateContact;
use App\Application\Actions\Contacts\DeleteContact;
use App\Application\Actions\Contacts\UpdateContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contacts\StoreContactRequest;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Contact::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) ($request->input('search') ?? $request->input('filter.search', '')));
        $clientId = $request->input('client_id') ?? $request->input('filter.client_id');

        $contacts = Contact::query()
            ->where('workspace_id', $workspace->id)
            ->with('client')
            ->when($clientId, fn ($query, $value) => $query->where('client_id', $value))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return ContactResource::collection($contacts);
    }

    public function store(
        StoreContactRequest $request,
        CreateContact $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        $contact = $action->execute($workspace->id, $request->user()->id, $request->validated())->load('client');

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'contact.created',
            Contact::class,
            $contact->id,
            null,
            $contact->toArray()
        );

        return (new ContactResource($contact))->response()->setStatusCode(201);
    }

    public function show(Contact $contact)
    {
        $workspace = app('currentWorkspace');

        abort_unless($contact->workspace_id === $workspace->id, 404);

        $this->authorize('view', $contact);

        return new ContactResource($contact->load('client'));
    }

    public function update(
        UpdateContactRequest $request,
        Contact $contact,
        UpdateContact $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($contact->workspace_id === $workspace->id, 404);

        $before = $contact->toArray();

        $contact = $action->execute($contact, $request->user()->id, $request->validated())->load('client');

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'contact.updated',
            Contact::class,
            $contact->id,
            $before,
            $contact->toArray()
        );

        return new ContactResource($contact);
    }

    public function destroy(
        Request $request,
        Contact $contact,
        DeleteContact $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($contact->workspace_id === $workspace->id, 404);

        $this->authorize('delete', $contact);

        $result = $action->execute($contact);
        if (!$result['deleted']) {
            return response()->json([
                'message' => 'This contact cannot be deleted while related events still exist.',
                'data' => $result['dependencies'],
            ], 409);
        }

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'contact.deleted',
            Contact::class,
            $contact->id,
            $result['before'],
            null
        );

        return response()->noContent();
    }

}
