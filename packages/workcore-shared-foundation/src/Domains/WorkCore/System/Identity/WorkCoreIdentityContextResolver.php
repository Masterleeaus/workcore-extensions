<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Identity;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;

final class WorkCoreIdentityContextResolver
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function resolve(Request $request, int $companyId, int $userId, object $membership): array
    {
        $worker = $this->db->table('tz_workers')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('employment_status', 'active')
            ->whereNull('deleted_at')
            ->first(['id', 'public_id']);
        $workerId = $worker === null ? null : (int) $worker->id;

        $requestedBranchId = $this->positiveHeader($request, 'X-Titan-Branch');
        $requestedTerritoryId = $this->positiveHeader($request, 'X-Titan-Territory');
        $branchId = null;
        $territoryId = null;

        if ($requestedTerritoryId !== null) {
            $territory = $this->db->table('tz_territories')
                ->where('company_id', $companyId)
                ->where('id', $requestedTerritoryId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->first(['id', 'branch_id']);
            if ($territory === null) {
                abort(409, 'The requested WorkCore territory is not active for this company.');
            }
            $territoryId = (int) $territory->id;
            $branchId = (int) $territory->branch_id;
        }

        if ($requestedBranchId !== null) {
            $branchExists = $this->db->table('tz_branches')
                ->where('company_id', $companyId)
                ->where('id', $requestedBranchId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists();
            if (! $branchExists) {
                abort(409, 'The requested WorkCore branch is not active for this company.');
            }
            if ($branchId !== null && $branchId !== $requestedBranchId) {
                abort(409, 'The requested WorkCore territory does not belong to the requested branch.');
            }
            $branchId = $requestedBranchId;
        }

        if ($territoryId === null && $worker !== null) {
            $assignableTypes = array_values(array_filter(
                (array) config('workcore.identity.worker_territory_assignable_types', ['worker', 'workforce_worker', 'tz_worker']),
                static fn ($value): bool => is_string($value) && trim($value) !== '',
            ));
            $today = now()->toDateString();
            $assignment = $this->db->table('tz_territory_assignments as assignment')
                ->join('tz_territories as territory', 'territory.id', '=', 'assignment.territory_id')
                ->where('assignment.company_id', $companyId)
                ->where('assignment.assignable_public_id', (string) $worker->public_id)
                ->whereIn('assignment.assignable_type', $assignableTypes)
                ->where('assignment.active', true)
                ->where('territory.status', 'active')
                ->whereNull('territory.deleted_at')
                ->where(static function ($query) use ($today): void {
                    $query->whereNull('assignment.effective_from')
                        ->orWhere('assignment.effective_from', '<=', $today);
                })
                ->where(static function ($query) use ($today): void {
                    $query->whereNull('assignment.effective_until')
                        ->orWhere('assignment.effective_until', '>=', $today);
                })
                ->orderBy('assignment.priority')
                ->orderBy('assignment.id')
                ->first(['territory.id as territory_id', 'territory.branch_id']);
            if ($assignment !== null) {
                $territoryId = (int) $assignment->territory_id;
                $branchId ??= (int) $assignment->branch_id;
            }
        }

        $deviceId = $request->header('X-Titan-Device');
        if ($deviceId !== null) {
            $deviceId = trim((string) $deviceId);
            if (! preg_match('/^[A-Za-z0-9._:-]{1,190}$/', $deviceId)) {
                abort(409, 'The WorkCore device identifier is invalid.');
            }
        }

        $authenticationAssurance = $request->attributes->get('authentication_assurance');
        if (! is_string($authenticationAssurance) || trim($authenticationAssurance) === '') {
            $authenticationAssurance = $request->attributes->getBoolean('mfa_verified') ? 'passport_mfa' : 'passport';
        }

        $membershipRevision = $this->revision($membership->updated_at ?? null);
        $securityRevision = max(
            $membershipRevision,
            $this->revision($this->db->table('tz_company_member_permissions')
                ->where('company_id', $companyId)
                ->where('membership_id', (int) $membership->id)
                ->max('updated_at')),
            isset($membership->role_id) && $membership->role_id !== null
                ? $this->revision($this->db->table('tz_company_role_permissions')
                    ->where('company_id', $companyId)
                    ->where('role_id', (int) $membership->role_id)
                    ->max('updated_at'))
                : 0,
        );

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'actor_subject' => 'magicai:user:' . $userId,
            'worker_id' => $workerId,
            'branch_id' => $branchId,
            'territory_id' => $territoryId,
            'device_id' => $deviceId,
            'authentication_assurance' => trim($authenticationAssurance),
            'security_revision' => $securityRevision,
            'membership_revision' => $membershipRevision,
        ];
    }

    private function positiveHeader(Request $request, string $header): ?int
    {
        $value = $request->header($header);
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (! ctype_digit((string) $value) || (int) $value < 1) {
            abort(409, "The {$header} header must contain a positive integer.");
        }

        return (int) $value;
    }

    private function revision(mixed $value): int
    {
        if ($value === null || trim((string) $value) === '') {
            return 0;
        }
        $timestamp = strtotime((string) $value);

        return $timestamp === false ? 0 : $timestamp;
    }
}
