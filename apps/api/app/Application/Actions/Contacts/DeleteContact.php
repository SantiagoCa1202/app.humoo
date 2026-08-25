<?php

namespace App\Application\Actions\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;

class DeleteContact
{
    public function execute(Contact $contact): array
    {
        return DB::transaction(function () use ($contact): array {
            $dependencies = ['events_count' => $contact->events()->count()];

            if (array_sum($dependencies) > 0) {
                return ['deleted' => false, 'dependencies' => $dependencies, 'before' => null];
            }

            $before = $contact->toArray();
            $contact->delete();

            return ['deleted' => true, 'dependencies' => $dependencies, 'before' => $before];
        });
    }
}
