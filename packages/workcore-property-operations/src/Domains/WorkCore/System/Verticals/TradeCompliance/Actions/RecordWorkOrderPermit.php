<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\TradeComplianceActionSupport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class RecordWorkOrderPermit implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private TradeComplianceActionSupport $support,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $workOrder = $this->support->workOrder($request->companyId, (string) $request->payload['work_order_public_id']);
        $itemId = $this->support->complianceItemId(
            $request->companyId,
            (int) $workOrder->id,
            $request->payload['requirement_code'] ?? null,
            'permit',
        );
        $publicId = (string) Str::ulid();
        $now = now();
        $this->db->table('tz_work_order_permits')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'work_order_id' => (int) $workOrder->id,
            'compliance_item_id' => $itemId,
            'permit_type' => trim((string) $request->payload['permit_type']),
            'permit_number' => $request->payload['permit_number'] ?? null,
            'authority' => $request->payload['authority'] ?? null,
            'jurisdiction' => $request->payload['jurisdiction'] ?? null,
            'status' => (string) ($request->payload['status'] ?? 'approved'),
            'issued_at' => $request->payload['issued_at'] ?? null,
            'expires_at' => $request->payload['expires_at'] ?? null,
            'conditions' => json_encode((array) ($request->payload['conditions'] ?? []), JSON_THROW_ON_ERROR),
            'document_path' => $request->payload['document_path'] ?? null,
            'recorded_by_user_id' => $request->actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ((string) ($request->payload['status'] ?? 'approved') === 'approved') {
            $this->support->satisfy(
                $request->companyId,
                (int) $workOrder->id,
                $request->payload['requirement_code'] ?? null,
                'permit',
                'work_order_permit',
                $publicId,
                $request->actorId,
            );
        }
        $record = (array) $this->db->table('tz_work_order_permits')->where('public_id', $publicId)->first();
        $record['conditions'] = (array) ($request->payload['conditions'] ?? []);
        return new ActionHandlerResult(
            $record,
            new TypedReference('work_order_permit', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.permit_recorded', 1, ['permit' => $record])],
        );
    }
}
