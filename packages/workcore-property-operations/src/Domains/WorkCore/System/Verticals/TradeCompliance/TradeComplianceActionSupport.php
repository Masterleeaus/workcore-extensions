<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class TradeComplianceActionSupport
{
    public function __construct(
        private ConnectionInterface $db,
        private WorkOrderComplianceGate $gate,
    ) {}

    public function workOrder(int $companyId, string $publicId): object
    {
        $record = $this->db->table('tz_work_orders')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();
        if ($record === null) {
            throw new InvalidArgumentException('Work order does not belong to the active company.');
        }
        return $record;
    }

    public function worker(int $companyId, ?string $publicId): ?object
    {
        if ($publicId === null || trim($publicId) === '') {
            return null;
        }
        $record = $this->db->table('tz_workers')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();
        if ($record === null) {
            throw new InvalidArgumentException('Worker does not belong to the active company.');
        }
        return $record;
    }

    public function complianceItemId(
        int $companyId,
        int $workOrderId,
        ?string $requirementCode,
        string $type,
    ): ?int {
        if ($requirementCode === null || trim($requirementCode) === '') {
            return null;
        }
        $item = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->where('requirement_code', trim($requirementCode))
            ->first();
        if ($item === null || (string) $item->requirement_type !== $type) {
            throw new InvalidArgumentException('Requirement code is not prepared for this evidence type.');
        }
        return (int) $item->id;
    }

    public function satisfy(
        int $companyId,
        int $workOrderId,
        ?string $requirementCode,
        string $type,
        string $recordType,
        string $recordPublicId,
        int $actorId,
    ): void {
        if ($requirementCode !== null && trim($requirementCode) !== '') {
            $this->gate->satisfyRequirement(
                $companyId,
                $workOrderId,
                trim($requirementCode),
                $type,
                $recordType,
                $recordPublicId,
                $actorId,
            );
            return;
        }
        $this->gate->satisfyFirstByType($companyId, $workOrderId, $type, $recordType, $recordPublicId, $actorId);
    }
}
