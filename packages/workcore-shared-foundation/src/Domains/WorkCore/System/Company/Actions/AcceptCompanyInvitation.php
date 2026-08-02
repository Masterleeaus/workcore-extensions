<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Company\Actions;

use App\Domains\WorkCore\System\Models\CompanyInvitation;
use App\Domains\WorkCore\System\Models\CompanyMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptCompanyInvitation
{
    public function execute(string $token, int $userId, string $userEmail): CompanyMember
    {
        return DB::transaction(function () use ($token, $userId, $userEmail): CompanyMember {
            $invitation = CompanyInvitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if ($invitation === null || $invitation->status !== 'pending' || $invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or expired.']);
            }
            if (strtolower(trim($userEmail)) !== strtolower($invitation->email)) {
                throw ValidationException::withMessages(['email' => 'This invitation belongs to another email address.']);
            }

            $member = CompanyMember::query()->updateOrCreate(
                ['company_id' => $invitation->company_id, 'user_id' => $userId],
                ['role_id' => $invitation->role_id, 'role_key' => $invitation->role_key, 'status' => 'active', 'is_owner' => false, 'joined_at' => now()]
            );
            $invitation->update(['status' => 'accepted', 'accepted_by_user_id' => $userId, 'accepted_at' => now()]);
            return $member;
        });
    }
}
