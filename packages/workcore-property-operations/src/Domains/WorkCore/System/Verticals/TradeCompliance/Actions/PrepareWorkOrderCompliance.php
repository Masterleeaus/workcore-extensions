<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\{TradeComplianceProfileResolver, WorkOrderComplianceGate};
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PrepareWorkOrderCompliance implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private TradeComplianceProfileResolver $profiles,
        private WorkOrderComplianceGate $gate,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $workOrder = $this->db->table('tz_work_orders')
            ->where('company_id', $request->companyId)
            ->where('public_id', (string) $request->payload['work_order_public_id'])
            ->whereNull('deleted_at')
            ->first();
        if ($workOrder === null) {
            throw new InvalidArgumentException('Work order does not belong to the active company.');
        }

        $profile = $this->profiles->resolveForWorkOrder($request->companyId, (int) $workOrder->id);
        $protected = $this->db->table('tz_work_order_compliance_items')
            ->where('company_id', $request->companyId)
            ->where('work_order_id', (int) $workOrder->id)
            ->whereIn('status', ['satisfied', 'waived'])
            ->pluck('requirement_code')
            ->all();
        $now = now();
        foreach ((array) $profile['requirements'] as $requirement) {
            if (in_array((string) $requirement['code'], $protected, true)) {
                continue;
            }
            $existingPublicId = $this->db->table('tz_work_order_compliance_items')
                ->where('company_id', $request->companyId)
                ->where('work_order_id', (int) $workOrder->id)
                ->where('requirement_code', (string) $requirement['code'])
                ->value('public_id');
            $this->db->table('tz_work_order_compliance_items')->updateOrInsert(
                [
                    'company_id' => $request->companyId,
                    'work_order_id' => (int) $workOrder->id,
                    'requirement_code' => (string) $requirement['code'],
                ],
                [
                    'public_id' => $existingPublicId ?: (string) Str::ulid(),
                    'profile_id' => $profile['profile_id'],
                    'requirement_type' => (string) $requirement['type'],
                    'label' => (string) $requirement['label'],
                    'required' => (bool) $requirement['required'],
                    'status' => 'pending',
                    'snapshot' => json_encode([
                        'profile_source' => $profile['source'],
                        'vertical_key' => $profile['vertical_key'],
                        'service_code' => $profile['service_code'],
                        'settings' => $requirement['settings'] ?? [],
                        'default_warranty_days' => $profile['default_warranty_days'],
                    ], JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $status = $this->gate->status($request->companyId, (int) $workOrder->id);
        return new ActionHandlerResult(
            ['work_order_public_id' => (string) $workOrder->public_id, 'profile' => $profile, 'gate' => $status],
            new TypedReference('work_order', (string) $workOrder->public_id),
            [new PendingDomainEvent('workcore.trade_compliance.work_order_prepared', 1, [
                'work_order_public_id' => (string) $workOrder->public_id,
                'profile_source' => $profile['source'],
            ])],
        );
    }
}
