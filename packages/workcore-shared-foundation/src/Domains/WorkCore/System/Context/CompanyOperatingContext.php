<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

final readonly class CompanyOperatingContext
{
    public function __construct(
        public string $locale,
        public string $timezone,
        public string $currencyCode,
        public string $countryCode,
    ) {
    }
}
