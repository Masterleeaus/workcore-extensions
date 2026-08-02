<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\{TradeComplianceActionSupport, TradeComplianceProfileResolver};
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class RegisterWorkOrderWarranty implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private TradeComplianceActionSupport $support,
        private TradeComplianceProfileResolver $profiles,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $workOrder = $this->support->workOrder($request->companyId, (string) $request->payload['work_order_public_id']);
        if ((string) $workOrder->status !== 'completed') {
            throw new DomainException('A warranty can only be activated for a completed work order.');
        }
        $startsOn = CarbonImmutable::parse((string) $request->payload['starts_on'])->startOfDay();
        $endsOn = trim((string) ($request->payload['ends_on'] ?? ''));
        if ($endsOn === '') {
            $profile = $this->profiles->resolveForWorkOrder($request->companyId, (int) $workOrder->id);
            $days = $profile['default_warranty_days'] ?? null;
            $endsOn = $days !== null ? $startsOn->addDays((int) $days)->toDateString() : null;
        }
        $publicId = (string) Str::ulid();
        $now = now();
        $this->db->table('tz_work_order_warranties')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'work_order_id' => (int) $workOrder->id,
            'warranty_type' => (string) ($request->payload['warranty_type'] ?? 'workmanship'),
            'status' => (string) ($request->payload['status'] ?? 'active'),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn,
            'terms' => $request->payload['terms'] ?? null,
            'covered_items' => json_encode((array) ($request->payload['covered_items'] ?? []), JSON_THROW_ON_ERROR),
            'created_by_user_id' => $request->actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $record = (array) $this->db->table('tz_work_order_warranties')->where('public_id', $publicId)->first();
        $record['covered_items'] = (array) ($request->payload['covered_items'] ?? []);
        return new ActionHandlerResult(
            $record,
            new TypedReference('work_order_warranty', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.warranty_registered', 1, ['warranty' => $record])],
        );
    }
}
