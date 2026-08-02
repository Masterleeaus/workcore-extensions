<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\TradeComplianceActionSupport;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateWorkOrderCallback implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private TradeComplianceActionSupport $support) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $source = $this->support->workOrder($request->companyId, (string) $request->payload['source_work_order_public_id']);
        $callback = $this->support->workOrder($request->companyId, (string) $request->payload['callback_work_order_public_id']);
        if ((int) $source->id === (int) $callback->id) {
            throw new InvalidArgumentException('The source and callback cannot be the same work order.');
        }
        if ((string) $source->status !== 'completed') {
            throw new DomainException('The source work order must be completed before a callback is linked.');
        }
        if ((int) $source->customer_id !== (int) $callback->customer_id) {
            throw new InvalidArgumentException('Source and callback work orders must belong to the same customer.');
        }
        if ($source->premises_id !== null && $callback->premises_id !== null && (int) $source->premises_id !== (int) $callback->premises_id) {
            throw new InvalidArgumentException('Source and callback work orders must refer to the same premises.');
        }
        if ($this->db->table('tz_work_order_callbacks')
            ->where('company_id', $request->companyId)
            ->where('callback_work_order_id', (int) $callback->id)
            ->where('status', 'open')
            ->whereNull('deleted_at')
            ->exists()) {
            throw new DomainException('The callback work order is already linked to an open callback.');
        }

        $warrantyId = null;
        $warrantyPublicId = trim((string) ($request->payload['warranty_public_id'] ?? ''));
        if ($warrantyPublicId !== '') {
            $warranty = $this->db->table('tz_work_order_warranties')
                ->where('company_id', $request->companyId)
                ->where('public_id', $warrantyPublicId)
                ->whereNull('deleted_at')
                ->first();
            if ($warranty === null || (int) $warranty->work_order_id !== (int) $source->id) {
                throw new InvalidArgumentException('Selected warranty does not belong to the source work order.');
            }
            $warrantyId = (int) $warranty->id;
        }

        $publicId = (string) Str::ulid();
        $now = now();
        $this->db->table('tz_work_order_callbacks')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'source_work_order_id' => (int) $source->id,
            'callback_work_order_id' => (int) $callback->id,
            'warranty_id' => $warrantyId,
            'reason_code' => trim((string) $request->payload['reason_code']),
            'reason' => $request->payload['reason'] ?? null,
            'chargeable' => (bool) ($request->payload['chargeable'] ?? false),
            'status' => 'open',
            'created_by_user_id' => $request->actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $record = (array) $this->db->table('tz_work_order_callbacks')->where('public_id', $publicId)->first();
        return new ActionHandlerResult(
            $record,
            new TypedReference('work_order_callback', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.callback_created', 1, [
                'callback' => $record,
                'source_work_order_public_id' => (string) $source->public_id,
                'callback_work_order_public_id' => (string) $callback->public_id,
            ])],
        );
    }
}
