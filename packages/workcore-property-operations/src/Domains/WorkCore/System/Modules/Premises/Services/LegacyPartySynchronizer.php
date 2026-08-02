<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisesContactLink;
use Illuminate\Support\Facades\DB;

/**
 * Pass 35 Stage D — party-role projection from canonical CRM contacts.
 *
 * Previously synchronised from premises-owned pm_property_contacts. Contact
 * identity now lives in tz_customer_contacts; this service projects a
 * PremisePartyRole snapshot from the canonical record via the link.
 */
class LegacyPartySynchronizer
{
    public function syncContactLink(PremisesContactLink $link): ?PremisePartyRole
    {
        $contact = DB::table('tz_customer_contacts')
            ->where('id', $link->customer_contact_id)
            ->where('company_id', $link->company_id)
            ->first();

        if ($contact === null) {
            return null;
        }

        return PremisePartyRole::updateOrCreate(
            [
                'company_id' => $link->company_id,
                'source_type' => 'premises_contact_link',
                'source_id' => $link->id,
            ],
            [
                'user_id' => $contact->portal_user_id ?? null,
                'premise_id' => $link->property_id,
                'party_type' => 'snapshot',
                'role' => $link->link_role ?: 'contact',
                'display_name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'organisation_name' => null,
                'metadata' => [
                    'notes' => $link->notes,
                    'projected_from' => 'tz_customer_contacts',
                    'customer_contact_id' => $link->customer_contact_id,
                ],
            ]
        );
    }

    public function retireContactLink(PremisesContactLink $link): void
    {
        $role = PremisePartyRole::where('company_id', $link->company_id)
            ->where('source_type', 'premises_contact_link')
            ->where('source_id', $link->id)
            ->first();

        if (!$role) {
            return;
        }

        if ($role->occupancies()->exists()) {
            $role->update([
                'valid_until' => now()->toDateString(),
                'metadata' => array_replace($role->metadata ?? [], ['contact_link_removed' => true]),
            ]);

            return;
        }

        $role->delete();
    }
}
