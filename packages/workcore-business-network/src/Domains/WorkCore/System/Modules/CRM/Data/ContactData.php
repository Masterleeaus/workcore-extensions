<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Data;

use App\Domains\WorkCore\System\Modules\CRM\Support\CrmNormalizer;

final readonly class ContactData
{
    public function __construct(
        public string $customerPublicId,
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $role = null,
        public bool $isPrimary = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            customerPublicId: trim((string) $data['customer_public_id']),
            name: trim((string) $data['name']),
            email: CrmNormalizer::email($data['email'] ?? null),
            phone: CrmNormalizer::phone($data['phone'] ?? null),
            role: isset($data['role']) ? trim((string) $data['role']) : null,
            isPrimary: (bool) ($data['is_primary'] ?? false),
        );
    }
}
