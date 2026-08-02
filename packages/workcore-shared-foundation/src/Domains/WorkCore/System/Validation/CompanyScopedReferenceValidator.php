<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Validation;

use App\Domains\WorkCore\System\Models\CompanyMember;
use App\Domains\WorkCore\System\Models\CompanyRole;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\ConnectionInterface;

final class CompanyScopedReferenceValidator
{
    public function __construct(private ConnectionInterface $db) {}

    public function requirePublicRecordId(int $companyId, string $table, string $publicId, string $field): int
    {
        $id = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->value('id');
        if (! $id) { throw ValidationException::withMessages([$field => ['The selected record does not belong to the current company.']]); }
        return (int) $id;
    }
    public function requireActiveMember(int $companyId, int $userId, string $field = 'user_id'): CompanyMember
    {
        $membership = CompanyMember::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                $field => ['The selected user is not an active member of the current company.'],
            ]);
        }

        return $membership;
    }

    public function requireCompanyRole(int $companyId, int $roleId, string $field = 'role_id'): CompanyRole
    {
        $role = CompanyRole::query()
            ->where('company_id', $companyId)
            ->whereKey($roleId)
            ->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                $field => ['The selected role does not belong to the current company.'],
            ]);
        }

        return $role;
    }
}
