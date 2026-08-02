<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Tenancy;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CompanyContextResolver;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialSettingsRepository;
use App\Domains\WorkCore\System\Modules\Finance\Tenancy\Exceptions\MissingCompanyContextException;
use Throwable;

/**
 * Pass 35 Stage A bridge: resolves the Finance domain's rich CompanyContext
 * (currency, timezone, country) from the canonical WorkCore tenant context
 * plus the company's tm_financial_settings row.
 *
 * WorkCore is the single tenancy authority. The Finance domain no longer
 * resolves companies on its own; it fails closed when WorkCore has no
 * resolved tenant or the company has no financial settings.
 */
final class WorkCoreCompanyContextResolver implements CompanyContextResolver
{
    public function __construct(
        private readonly TenantContextContract $tenantContext,
        private readonly FinancialSettingsRepository $financialSettings,
    ) {}

    public function resolve(): CompanyContext
    {
        if (! $this->tenantContext->hasTenant()) {
            throw MissingCompanyContextException::forFinanceAction();
        }

        $companyId = (string) $this->tenantContext->companyId();

        try {
            $settings = $this->financialSettings->getForCompany($companyId);
        } catch (Throwable) {
            throw MissingCompanyContextException::forFinanceAction();
        }

        return new CompanyContext(
            companyId: $companyId,
            userId: $this->tenantContext->userId() !== null ? (string) $this->tenantContext->userId() : null,
            membershipId: null,
            baseCurrency: $settings->baseCurrency,
            timezone: $settings->timezone,
            countryCode: $settings->countryCode,
        );
    }
}
