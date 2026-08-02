<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Company\Actions;

use App\Domains\WorkCore\System\Models\Company;
use App\Domains\WorkCore\System\Models\CompanyMember;
use App\Domains\WorkCore\System\Models\CompanyRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransferCompanyOwnership
{
    public function execute(Company $company, int $newOwnerUserId): Company
    {
        return DB::transaction(function () use ($company, $newOwnerUserId): Company {
            $membership = CompanyMember::query()
                ->where('company_id', $company->getKey())
                ->where('user_id', $newOwnerUserId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw ValidationException::withMessages(['new_owner_user_id' => 'The new owner must be an active company member.']);
            }

            $ownerRole = CompanyRole::query()->where('company_id', $company->getKey())->where('key', 'owner')->firstOrFail();
            CompanyMember::query()->where('company_id', $company->getKey())->update(['is_owner' => false]);
            CompanyMember::query()->whereKey($membership->getKey())->update([
                'is_owner' => true,
                'role_id' => $ownerRole->getKey(),
                'role_key' => 'owner',
            ]);
            $company->update(['owner_user_id' => $newOwnerUserId]);

            return $company->fresh(['memberships']);
        });
    }
}
