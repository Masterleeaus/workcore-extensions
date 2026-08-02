<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Data;

final readonly class PremisesData
{
    public function __construct(
        public string $customerPublicId,
        public string $name,
        public string $addressLine1,
        public ?string $addressLine2 = null,
        public ?string $suburb = null,
        public ?string $state = null,
        public ?string $postcode = null,
        public string $countryCode = 'AU',
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $accessInstructions = null,
        public ?string $parkingInstructions = null,
        public ?string $hazards = null,
        public ?string $serviceRequirements = null,
        public bool $isPrimary = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $nullable = static fn (string $key): ?string => isset($data[$key]) && trim((string) $data[$key]) !== '' ? trim((string) $data[$key]) : null;
        return new self(
            customerPublicId: trim((string) $data['customer_public_id']),
            name: trim((string) $data['name']),
            addressLine1: trim((string) $data['address_line_1']),
            addressLine2: $nullable('address_line_2'),
            suburb: $nullable('suburb'),
            state: $nullable('state'),
            postcode: $nullable('postcode'),
            countryCode: strtoupper(trim((string) ($data['country_code'] ?? 'AU'))),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            accessInstructions: $nullable('access_instructions'),
            parkingInstructions: $nullable('parking_instructions'),
            hazards: $nullable('hazards'),
            serviceRequirements: $nullable('service_requirements'),
            isPrimary: (bool) ($data['is_primary'] ?? false),
        );
    }
}
