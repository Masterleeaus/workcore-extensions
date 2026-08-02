<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Settings;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use DateTimeZone;
use InvalidArgumentException;

final readonly class FinancialSettings
{
    public string $countryCode;

    public function __construct(
        public string $companyId,
        public CurrencyCode $baseCurrency,
        public string $timezone,
        string $countryCode,
        public bool $gstRegistered,
        public GstAccountingBasis $gstAccountingBasis,
        public bool $pricesIncludeTax,
        public int $financialYearStartMonth,
        public int $financialYearStartDay,
        public int $defaultPaymentTermsDays,
    ) {
        if (trim($companyId) === '') {
            throw new InvalidArgumentException('Financial settings require a company ID.');
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Financial settings require a valid IANA timezone.');
        }

        $normalizedCountry = strtoupper(trim($countryCode));
        if (preg_match('/^[A-Z]{2}$/', $normalizedCountry) !== 1) {
            throw new InvalidArgumentException('Financial settings require a two-letter country code.');
        }

        if (! checkdate($financialYearStartMonth, $financialYearStartDay, 2000)) {
            throw new InvalidArgumentException('Financial-year start month and day do not form a valid date.');
        }

        if ($defaultPaymentTermsDays < 0 || $defaultPaymentTermsDays > 3650) {
            throw new InvalidArgumentException('Default payment terms must be between 0 and 3650 days.');
        }

        $this->countryCode = $normalizedCountry;
    }

    /** @return array<string, bool|int|string> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'base_currency' => $this->baseCurrency->value,
            'timezone' => $this->timezone,
            'country_code' => $this->countryCode,
            'gst_registered' => $this->gstRegistered,
            'gst_accounting_basis' => $this->gstAccountingBasis->value,
            'prices_include_tax' => $this->pricesIncludeTax,
            'financial_year_start' => sprintf('%02d-%02d', $this->financialYearStartMonth, $this->financialYearStartDay),
            'default_payment_terms_days' => $this->defaultPaymentTermsDays,
        ];
    }
}
