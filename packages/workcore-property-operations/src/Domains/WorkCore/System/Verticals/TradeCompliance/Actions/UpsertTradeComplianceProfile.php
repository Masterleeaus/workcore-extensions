<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\TradeCompliancePolicy;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UpsertTradeComplianceProfile implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private TradeCompliancePolicy $policy,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $verticalKey = trim((string) $request->payload['vertical_key']);
        if (! $this->db->table('tz_company_verticals')
            ->where('company_id', $request->companyId)
            ->where('vertical_key', $verticalKey)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists()) {
            throw new DomainException("Vertical [{$verticalKey}] is not active for the company.");
        }

        $businessLineId = null;
        $businessLinePublicId = trim((string) ($request->payload['business_line_public_id'] ?? ''));
        if ($businessLinePublicId !== '') {
            $line = $this->db->table('tz_business_lines as bl')
                ->join('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
                ->where('bl.company_id', $request->companyId)
                ->where('bl.public_id', $businessLinePublicId)
                ->where('bl.status', 'active')
                ->where('cv.status', 'active')
                ->whereNull('bl.deleted_at')
                ->first(['bl.id', 'cv.vertical_key']);
            if ($line === null || (string) $line->vertical_key !== $verticalKey) {
                throw new InvalidArgumentException('Business line must belong to the selected active trade vertical.');
            }
            $businessLineId = (int) $line->id;
        }

        $requirements = $this->policy->normaliseRequirements((array) $request->payload['requirements']);
        $publicId = trim((string) ($request->payload['profile_public_id'] ?? ''));
        $existing = null;
        if ($publicId !== '') {
            $existing = $this->db->table('tz_trade_compliance_profiles')
                ->where('company_id', $request->companyId)
                ->where('public_id', $publicId)
                ->first();
            if ($existing === null) {
                throw new InvalidArgumentException('Trade compliance profile does not belong to the active company.');
            }
        } else {
            $publicId = (string) Str::ulid();
        }

        $now = now();
        $values = [
            'company_id' => $request->companyId,
            'business_line_id' => $businessLineId,
            'vertical_key' => $verticalKey,
            'service_code' => $request->payload['service_code'] ?? null,
            'jurisdiction' => (string) ($request->payload['jurisdiction'] ?? 'AU-VIC'),
            'name' => trim((string) $request->payload['name']),
            'status' => (string) ($request->payload['status'] ?? 'active'),
            'effective_from' => $request->payload['effective_from'] ?? null,
            'effective_to' => $request->payload['effective_to'] ?? null,
            'requirements' => json_encode($requirements, JSON_THROW_ON_ERROR),
            'default_warranty_days' => $request->payload['default_warranty_days'] ?? null,
            'settings' => json_encode((array) ($request->payload['settings'] ?? []), JSON_THROW_ON_ERROR),
            'updated_by_user_id' => $request->actorId,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        if ($existing !== null) {
            $this->db->table('tz_trade_compliance_profiles')->where('id', (int) $existing->id)->update($values);
        } else {
            $this->db->table('tz_trade_compliance_profiles')->insert($values + [
                'public_id' => $publicId,
                'created_by_user_id' => $request->actorId,
                'created_at' => $now,
            ]);
        }

        $record = (array) $this->db->table('tz_trade_compliance_profiles')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->first();
        $record['requirements'] = $requirements;
        $record['settings'] = (array) ($request->payload['settings'] ?? []);

        return new ActionHandlerResult(
            $record,
            new TypedReference('trade_compliance_profile', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.profile_upserted', 1, ['profile' => $record])],
        );
    }
}
