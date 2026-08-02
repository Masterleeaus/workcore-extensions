<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Company\Actions;

use App\Domains\WorkCore\System\Models\Company;

final class SuspendCompany
{
    public function execute(Company $company): Company
    {
        $company->update(['status' => 'suspended']);
        return $company->fresh();
    }
}
