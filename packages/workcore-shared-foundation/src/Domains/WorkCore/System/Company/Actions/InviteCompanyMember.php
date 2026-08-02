<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Company\Actions;

use App\Domains\WorkCore\System\Models\Company;
use App\Domains\WorkCore\System\Models\CompanyInvitation;
use App\Domains\WorkCore\System\Models\CompanyRole;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class InviteCompanyMember
{
    /** @return array{invitation: CompanyInvitation, token: string} */
    public function execute(Company $company, string $email, string $roleKey, int $invitedByUserId): array
    {
        $role = CompanyRole::query()->where('company_id', $company->getKey())->where('key', $roleKey)->first();
        if ($role === null || $roleKey === 'owner') {
            throw ValidationException::withMessages(['role_key' => 'The selected company role is invalid for invitations.']);
        }

        $token = Str::random(64);
        $invitation = CompanyInvitation::query()->create([
            'company_id' => $company->getKey(), 'role_id' => $role->getKey(), 'email' => strtolower(trim($email)),
            'role_key' => $roleKey, 'token_hash' => hash('sha256', $token), 'status' => 'pending',
            'invited_by_user_id' => $invitedByUserId, 'expires_at' => now()->addDays(7),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }
}
