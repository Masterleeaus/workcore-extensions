<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisesContactLink;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Services\LegacyPartySynchronizer;
use InvalidArgumentException;

/**
 * Pass 35 Stage D — links an existing canonical CRM contact to a premise.
 *
 * This action no longer creates contact identity. Callers must supply a
 * customer_contact_id that already exists in tz_customer_contacts.
 */
class AddPremiseContactAction
{
    public function handle(Property $property, array $data): PremisesContactLink
    {
        if (empty($data['customer_contact_id'])) {
            throw new InvalidArgumentException(
                'A canonical customer_contact_id is required; premises do not create contacts.'
            );
        }

        $link = PremisesContactLink::updateOrCreate(
            [
                'property_id' => $property->id,
                'customer_contact_id' => $data['customer_contact_id'],
                'link_role' => $data['link_role'] ?? 'contact',
            ],
            [
                'company_id' => $property->company_id,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]
        );

        app(LegacyPartySynchronizer::class)->syncContactLink($link);

        return $link;
    }
}
