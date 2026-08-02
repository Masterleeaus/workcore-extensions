<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Identity;

use App\Domains\WorkCore\System\Models\Company;
use App\Domains\WorkCore\System\Models\CompanyMember;
use Illuminate\Contracts\Auth\Authenticatable;

final class MagicAIUserCompanyAdapter
{
    public function activeCompanyId(Authenticatable $user): ?int
    {
        $value = $user->getAuthIdentifier() !== null
            ? $user->getAttribute((string) config('workcore.identity.active_company_column', 'active_company_id'))
            : null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function belongsToCompany(int $userId, int $companyId): bool
    {
        return $this->membership($userId, $companyId) !== null;
    }

    public function membership(int $userId, int $companyId): ?CompanyMember
    {
        $companyIsActive = Company::query()
            ->whereKey($companyId)
            ->where('status', 'active')
            ->exists();

        if (! $companyIsActive) {
            return null;
        }

        return CompanyMember::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->first();
    }
}
