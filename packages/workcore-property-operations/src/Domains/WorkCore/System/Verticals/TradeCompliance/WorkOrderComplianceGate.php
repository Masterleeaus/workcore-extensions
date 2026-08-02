<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance;

use DomainException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class WorkOrderComplianceGate
{
    public function __construct(
        private ConnectionInterface $db,
        private TradeCompliancePolicy $policy,
    ) {}

    public function assertReadyForCompletion(int $companyId, int $workOrderId): void
    {
        $status = $this->status($companyId, $workOrderId);
        if (! $status['ready']) {
            throw new DomainException('Work-order compliance requirements are incomplete: ' . implode(', ', $status['blocking_codes']));
        }
    }

    public function assertReadyForCompletionByPublicId(int $companyId, string $workOrderPublicId): void
    {
        $workOrderId = $this->workOrderId($companyId, $workOrderPublicId);
        $this->assertReadyForCompletion($companyId, $workOrderId);
    }

    public function assertReadyForCertificateIssue(int $companyId, int $workOrderId): void
    {
        $items = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->where('requirement_type', '!=', 'certificate')
            ->orderBy('id')
            ->get()
            ->map(static fn (object $item): array => (array) $item)
            ->all();
        $status = $this->policy->gateStatus($items);
        if (! $status['ready']) {
            throw new DomainException('Work-order compliance requirements are incomplete before certificate issue: ' . implode(', ', $status['blocking_codes']));
        }
    }

    /** @return array<string,mixed> */
    public function status(int $companyId, int $workOrderId): array
    {
        $items = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $item): array => (array) $item)
            ->all();
        $status = $this->policy->gateStatus($items);
        // Explicit field retained for callers and tests that distinguish legacy jobs.
        $status['legacy_unprepared'] = (bool) $status['legacy_unprepared'];
        $status['items'] = $items;
        return $status;
    }

    public function satisfyRequirement(
        int $companyId,
        int $workOrderId,
        string $requirementCode,
        string $expectedType,
        string $satisfiedByType,
        string $satisfiedByPublicId,
        int $actorId,
    ): void {
        $item = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->where('requirement_code', $requirementCode)
            ->first();
        if ($item === null) {
            throw new InvalidArgumentException("Compliance requirement [{$requirementCode}] is not prepared for the work order.");
        }
        if ((string) $item->requirement_type !== $expectedType) {
            throw new InvalidArgumentException("Compliance requirement [{$requirementCode}] does not accept [{$expectedType}] evidence.");
        }
        $this->db->table('tz_work_order_compliance_items')->where('id', (int) $item->id)->update([
            'status' => 'satisfied',
            'satisfied_by_type' => $satisfiedByType,
            'satisfied_by_public_id' => $satisfiedByPublicId,
            'satisfied_at' => now(),
            'satisfied_by_user_id' => $actorId,
            'waiver_reason' => null,
            'waived_by_user_id' => null,
            'updated_at' => now(),
        ]);
    }

    public function satisfyFirstByType(
        int $companyId,
        int $workOrderId,
        string $requirementType,
        string $satisfiedByType,
        string $satisfiedByPublicId,
        int $actorId,
    ): bool {
        $item = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->where('requirement_type', $requirementType)
            ->whereNotIn('status', ['satisfied', 'waived'])
            ->orderByDesc('required')
            ->orderBy('id')
            ->first();
        if ($item === null) {
            return false;
        }
        $this->satisfyRequirement(
            $companyId,
            $workOrderId,
            (string) $item->requirement_code,
            $requirementType,
            $satisfiedByType,
            $satisfiedByPublicId,
            $actorId,
        );
        return true;
    }

    public function waiveRequirement(int $companyId, int $workOrderId, string $requirementCode, string $reason, int $actorId): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A waiver reason is required.');
        }
        $updated = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->where('requirement_code', $requirementCode)
            ->update([
                'status' => 'waived',
                'waiver_reason' => trim($reason),
                'waived_by_user_id' => $actorId,
                'satisfied_at' => now(),
                'updated_at' => now(),
            ]);
        if ($updated < 1) {
            throw new InvalidArgumentException('Compliance requirement does not belong to the active work order.');
        }
    }

    public function workOrderId(int $companyId, string $publicId): int
    {
        $id = $this->db->table('tz_work_orders')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->value('id');
        if ($id === null) {
            throw new InvalidArgumentException('Work order does not belong to the active company.');
        }
        return (int) $id;
    }
}
