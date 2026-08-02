<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Settings\FinancialSettings;

interface FinancialSettingsRepository
{
    public function getForCompany(string $companyId): FinancialSettings;

    public function save(FinancialSettings $settings, AuditActor $actor): void;
}
