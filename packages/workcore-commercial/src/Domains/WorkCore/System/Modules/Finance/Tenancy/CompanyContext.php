<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Tenancy;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use DateTimeZone;
use InvalidArgumentException;

final readonly class CompanyContext
{
    public string $countryCode;

    public function __construct(
        public string $companyId,
        public ?string $userId,
        public ?string $membershipId,
        public CurrencyCode $baseCurrency,
        public string $timezone,
        string $countryCode,
    ) {
        if (trim($companyId) === '') {
            throw new InvalidArgumentException('Company context requires a non-blank company ID.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Company context contains an invalid IANA timezone.');
        }

        $normalizedCountry = strtoupper(trim($countryCode));
        if (preg_match('/^[A-Z]{2}$/', $normalizedCountry) !== 1) {
            throw new InvalidArgumentException('Country code must contain exactly two ASCII letters.');
        }

        $this->countryCode = $normalizedCountry;
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'membership_id' => $this->membershipId,
            'base_currency' => $this->baseCurrency->value,
            'timezone' => $this->timezone,
            'country_code' => $this->countryCode,
        ];
    }
}
