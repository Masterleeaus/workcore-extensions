<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\TradeComplianceActionSupport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class RecordWorkOrderSafetyControl implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private TradeComplianceActionSupport $support) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $workOrder = $this->support->workOrder($request->companyId, (string) $request->payload['work_order_public_id']);
        $worker = $this->support->worker($request->companyId, $request->payload['worker_public_id'] ?? null);
        $itemId = $this->support->complianceItemId(
            $request->companyId,
            (int) $workOrder->id,
            $request->payload['requirement_code'] ?? null,
            'safety_control',
        );
        $publicId = (string) Str::ulid();
        $status = (string) ($request->payload['status'] ?? 'completed');
        $now = now();
        $this->db->table('tz_work_order_safety_controls')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'work_order_id' => (int) $workOrder->id,
            'compliance_item_id' => $itemId,
            'worker_id' => $worker?->id,
            'control_type' => trim((string) $request->payload['control_type']),
            'status' => $status,
            'completed_at' => $request->payload['completed_at'] ?? ($status === 'completed' ? $now : null),
            'details' => json_encode((array) ($request->payload['details'] ?? []), JSON_THROW_ON_ERROR),
            'notes' => $request->payload['notes'] ?? null,
            'completed_by_user_id' => $status === 'completed' ? $request->actorId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($status === 'completed') {
            $this->support->satisfy(
                $request->companyId,
                (int) $workOrder->id,
                $request->payload['requirement_code'] ?? null,
                'safety_control',
                'work_order_safety_control',
                $publicId,
                $request->actorId,
            );
        }
        $record = (array) $this->db->table('tz_work_order_safety_controls')->where('public_id', $publicId)->first();
        $record['details'] = (array) ($request->payload['details'] ?? []);
        return new ActionHandlerResult(
            $record,
            new TypedReference('work_order_safety_control', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.safety_control_recorded', 1, ['control' => $record])],
        );
    }
}
