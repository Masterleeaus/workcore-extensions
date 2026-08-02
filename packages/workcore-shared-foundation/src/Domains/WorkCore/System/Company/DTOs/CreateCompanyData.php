<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Company\DTOs;

final readonly class CreateCompanyData
{
    public function __construct(
        public int $ownerUserId,
        public string $name,
        public string $slug,
        public string $timezone = 'Australia/Melbourne',
        public string $currencyCode = 'AUD',
        public string $countryCode = 'AU',
        public ?string $abn = null,
        public bool $gstRegistered = false,
    ) {}
}
