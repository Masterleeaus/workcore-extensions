<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\ClaimLineState;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class PrepareNDISClaimLine implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $delivery = $this->records->delivery($request->companyId, (string) $payload['delivery_public_id']);
        if ((int) $delivery->participant_id !== (int) $participant->id) {
            throw new DomainException('Claim delivery belongs to another participant.');
        }
        if ((string) $delivery->status !== 'approved' || $delivery->approved_at === null) {
            throw new DomainException('Only an approved support delivery can be prepared for claiming.');
        }

        $agreement = $this->db->table('tz_ndis_service_agreements')
            ->where('company_id', $request->companyId)
            ->where('id', (int) $delivery->agreement_id)
            ->first();
        $item = $this->db->table('tz_ndis_service_agreement_items')
            ->where('company_id', $request->companyId)
            ->where('id', (int) $delivery->agreement_item_id)
            ->first();
        if ($agreement === null || $item === null) {
            throw new DomainException('Claim source agreement or item is unavailable.');
        }

        $status = (string) ($payload['status'] ?? 'prepared');
        ClaimLineState::assertAllowed($status);
        if (! in_array($status, ['prepared', 'held'], true)) {
            throw new DomainException('A new claim line must begin as prepared or held.');
        }

        $claimType = (string) $item->claim_type;
        if (isset($payload['claim_type']) && (string) $payload['claim_type'] !== $claimType) {
            throw new DomainException('Claim type must match the service agreement item.');
        }

        $existing = $this->db->table('tz_ndis_claim_lines')
            ->where('company_id', $request->companyId)
            ->where('delivery_id', (int) $delivery->id)
            ->where('claim_type', $claimType)
            ->whereNull('deleted_at')
            ->first();
        if ($existing !== null && ! in_array((string) $existing->status, ['prepared', 'held', 'rejected'], true)) {
            throw new DomainException('This delivery already has a claim line that cannot be regenerated.');
        }

        $publicId = (string) ($existing->public_id ?? Str::ulid());
        $rate = round((float) $delivery->unit_rate_snapshot, 4);
        $quantity = round((float) $delivery->quantity, 4);
        $amount = round((float) $delivery->claimable_amount, 4);
        if ($rate < 0 || $quantity < 0 || $amount < 0) {
            throw new DomainException('Claim quantity, rate and amount cannot be negative.');
        }
        if (abs(round($quantity * $rate, 4) - $amount) > 0.0001) {
            throw new DomainException('Approved delivery financial snapshots are internally inconsistent.');
        }

        $values = [
            'company_id' => $request->companyId,
            'participant_id' => (int) $participant->id,
            'plan_id' => $delivery->plan_id,
            'plan_budget_id' => $item->plan_budget_id,
            'agreement_id' => (int) $agreement->id,
            'agreement_item_id' => (int) $item->id,
            'delivery_id' => (int) $delivery->id,
            'claim_reference' => $payload['claim_reference'] ?? ($existing->claim_reference ?? null),
            'external_batch_reference' => $existing->external_batch_reference ?? null,
            'external_claim_reference' => $existing->external_claim_reference ?? null,
            'support_item_code' => (string) $item->support_item_code,
            'claim_type' => $claimType,
            'service_date' => (string) $delivery->service_date,
            'unit' => (string) $delivery->unit,
            'quantity' => $quantity,
            'rate_snapshot' => $rate,
            'amount' => $amount,
            'gst_code' => (string) ($item->gst_code ?? 'GST_FREE'),
            'funding_management' => (string) ($agreement->funding_management ?? $participant->funding_management ?? 'unknown'),
            'status' => $status,
            'hold_reason' => $payload['hold_reason'] ?? null,
            'rejection_reason' => null,
            'finance_provider' => $payload['finance_provider'] ?? null,
            'finance_external_id' => $payload['finance_external_id'] ?? null,
            'metadata' => json_encode((array) ($payload['metadata'] ?? []), JSON_THROW_ON_ERROR),
            'prepared_by_user_id' => $request->actorId,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            $this->db->table('tz_ndis_claim_lines')->insert($values + [
                'public_id' => $publicId,
                'created_at' => now(),
            ]);
        } else {
            $this->db->table('tz_ndis_claim_lines')->where('id', (int) $existing->id)->update($values);
        }

        $data = [
            'public_id' => $publicId,
            'participant_public_id' => (string) $participant->public_id,
            'delivery_public_id' => (string) $delivery->public_id,
            'status' => $status,
            'amount' => $amount,
            'rate_snapshot' => $rate,
            'external_submission' => false,
        ];
        return new ActionHandlerResult(
            $data,
            new TypedReference('ndis_claim_line', $publicId),
            [new PendingDomainEvent('workcore.ndis.claim.prepared', 1, $data)],
        );
    }
}
