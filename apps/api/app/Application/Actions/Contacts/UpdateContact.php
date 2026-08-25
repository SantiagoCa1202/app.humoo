<?php

namespace App\Application\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class UpdateContact
{
    public function __construct(private CreateContact $createContact)
    {
    }

    public function execute(Contact $contact, string $userId, array $attributes): Contact
    {
        return DB::transaction(function () use ($attributes, $contact, $userId): Contact {
            $contact->forceFill([
                ...$attributes,
                'updated_by' => $userId,
            ])->save();

            $this->createContact->syncPrimaryFlag($contact);

            return $contact->fresh();
        });
    }
}
