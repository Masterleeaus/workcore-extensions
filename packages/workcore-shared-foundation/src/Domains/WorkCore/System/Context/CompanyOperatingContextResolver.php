<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

use App\Domains\WorkCore\System\Models\Company;

final class CompanyOperatingContextResolver
{
    public function resolve(int $companyId, ?string $requestLocale = null): CompanyOperatingContext
    {
        $company = Company::query()->findOrFail($companyId);
        $settings = is_array($company->settings) ? $company->settings : [];

        return new CompanyOperatingContext(
            locale: $this->nonEmpty($settings['locale'] ?? $requestLocale, (string) config('workcore.context.default_locale', 'en_AU')),
            timezone: $this->nonEmpty($company->timezone, (string) config('workcore.context.default_timezone', 'Australia/Melbourne')),
            currencyCode: $this->nonEmpty($company->currency_code, (string) config('workcore.context.default_currency', 'AUD')),
            countryCode: $this->nonEmpty($company->country_code, (string) config('workcore.context.default_country', 'AU')),
        );
    }

    private function nonEmpty(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : $fallback;
    }
}
