<?php

namespace App\Application\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class CreateContact
{
    public function execute(string $workspaceId, string $userId, array $attributes): Contact
    {
        return DB::transaction(function () use ($attributes, $userId, $workspaceId): Contact {
            $contact = Contact::query()->create([
                ...$attributes,
                'workspace_id' => $workspaceId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'is_primary' => (bool) ($attributes['is_primary'] ?? false),
            ]);

            $this->syncPrimaryFlag($contact);

            return $contact->fresh();
        });
    }

    public function syncPrimaryFlag(Contact $contact): void
    {
        if (!$contact->client_id || !$contact->is_primary) {
            return;
        }

        Contact::query()
            ->where('workspace_id', $contact->workspace_id)
            ->where('client_id', $contact->client_id)
            ->where('id', '!=', $contact->id)
            ->update(['is_primary' => false]);
    }
}
