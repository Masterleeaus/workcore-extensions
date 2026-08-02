<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\ServiceAgreementState;
use App\Domains\WorkCore\System\References\TypedReference;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class UpsertNDISServiceAgreement implements BusinessActionHandlerContract
{
    private const UNITS = ['hour', 'minute', 'each', 'day', 'week', 'month', 'kilometre'];
    private const CLAIM_TYPES = ['standard', 'travel', 'cancellation', 'non_face_to_face', 'other'];
    private const ITEM_STATUSES = ['active', 'inactive', 'expired'];
    private const FUNDING_TYPES = ['agency_managed', 'plan_managed', 'self_managed', 'mixed', 'unknown'];

    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $status = (string) ($payload['status'] ?? 'draft');
        ServiceAgreementState::assertAllowed($status);

        $startsOn = new DateTimeImmutable((string) $payload['starts_on']);
        $endsOn = isset($payload['ends_on']) && $payload['ends_on'] !== null
            ? new DateTimeImmutable((string) $payload['ends_on'])
            : null;
        if ($endsOn !== null && $endsOn < $startsOn) {
            throw new DomainException('Service agreement end date must not precede its start date.');
        }

        $planId = null;
        $plan = null;
        if (! empty($payload['plan_public_id'])) {
            $plan = $this->records->plan($request->companyId, (string) $payload['plan_public_id']);
            if ((int) $plan->participant_id !== (int) $participant->id) {
                throw new DomainException('Service agreement plan belongs to another participant.');
            }
            $planStarts = new DateTimeImmutable((string) $plan->starts_on);
            $planEnds = new DateTimeImmutable((string) $plan->ends_on);
            if ($startsOn < $planStarts || ($endsOn ?? $startsOn) > $planEnds) {
                throw new DomainException('Service agreement dates must fall within the selected NDIS plan.');
            }
            if ($status === 'active' && (string) $plan->status !== 'active') {
                throw new DomainException('An active service agreement requires an active NDIS plan.');
            }
            $planId = (int) $plan->id;
        }

        $fundingManagement = (string) ($payload['funding_management'] ?? $participant->funding_management ?? 'unknown');
        if (! in_array($fundingManagement, self::FUNDING_TYPES, true)) {
            throw new DomainException('Unsupported service agreement funding-management type.');
        }

        $publicId = trim((string) ($payload['agreement_public_id'] ?? '')) ?: (string) Str::ulid();
        $itemPublicIds = $this->db->transaction(function () use (
            $request,
            $payload,
            $participant,
            $planId,
            $status,
            $publicId,
            $startsOn,
            $endsOn,
            $fundingManagement,
        ): array {
            $existing = $this->db->table('tz_ndis_service_agreements')
                ->where('company_id', $request->companyId)
                ->where('public_id', $publicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null && (int) $existing->participant_id !== (int) $participant->id) {
                throw new DomainException('Service agreement belongs to another participant.');
            }
            if ($existing !== null && (string) $existing->status !== $status && ! ServiceAgreementState::canTransition((string) $existing->status, $status)) {
                throw new DomainException("Service agreement cannot transition from [{$existing->status}] to [{$status}].");
            }

            $signedAt = $payload['signed_at'] ?? ($existing->signed_at ?? null);
            if (in_array($status, ['signed', 'active'], true) && empty($signedAt)) {
                throw new DomainException('Signed and active service agreements require a signed timestamp.');
            }

            $values = [
                'company_id' => $request->companyId,
                'participant_id' => (int) $participant->id,
                'plan_id' => $planId,
                'agreement_number' => trim((string) ($payload['agreement_number'] ?? $existing->agreement_number ?? '')),
                'status' => $status,
                'starts_on' => (string) $payload['starts_on'],
                'ends_on' => $payload['ends_on'] ?? ($existing->ends_on ?? null),
                'signed_at' => $signedAt,
                'funding_management' => $fundingManagement,
                'currency' => strtoupper((string) ($payload['currency'] ?? $existing->currency ?? 'AUD')),
                'cancellation_terms' => json_encode((array) ($payload['cancellation_terms'] ?? $this->records->decode($existing->cancellation_terms ?? null)), JSON_THROW_ON_ERROR),
                'travel_terms' => json_encode((array) ($payload['travel_terms'] ?? $this->records->decode($existing->travel_terms ?? null)), JSON_THROW_ON_ERROR),
                'notes' => $payload['notes'] ?? ($existing->notes ?? null),
                'metadata' => json_encode((array) ($payload['metadata'] ?? $this->records->decode($existing->metadata ?? null)), JSON_THROW_ON_ERROR),
                'updated_by_user_id' => $request->actorId,
                'updated_at' => now(),
            ];
            if ($values['agreement_number'] === '') {
                throw new DomainException('Service agreement number is required.');
            }

            if ($existing === null) {
                $this->db->table('tz_ndis_service_agreements')->insert($values + [
                    'public_id' => $publicId,
                    'created_by_user_id' => $request->actorId,
                    'created_at' => now(),
                ]);
                $agreementId = (int) $this->db->table('tz_ndis_service_agreements')
                    ->where('company_id', $request->companyId)
                    ->where('public_id', $publicId)
                    ->value('id');
            } else {
                $agreementId = (int) $existing->id;
                $this->db->table('tz_ndis_service_agreements')->where('id', $agreementId)->update($values);
            }

            $result = [];
            foreach ((array) ($payload['items'] ?? []) as $item) {
                $code = trim((string) ($item['support_item_code'] ?? ''));
                $name = trim((string) ($item['support_item_name'] ?? ''));
                if ($code === '' || $name === '') {
                    throw new DomainException('Every service agreement item requires a support item code and name.');
                }

                $unit = (string) ($item['unit'] ?? 'hour');
                if (! in_array($unit, self::UNITS, true)) {
                    throw new DomainException("Unsupported service agreement item unit [{$unit}].");
                }
                $claimType = (string) ($item['claim_type'] ?? 'standard');
                if (! in_array($claimType, self::CLAIM_TYPES, true)) {
                    throw new DomainException("Unsupported service agreement claim type [{$claimType}].");
                }
                $itemStatus = (string) ($item['status'] ?? 'active');
                if (! in_array($itemStatus, self::ITEM_STATUSES, true)) {
                    throw new DomainException("Unsupported service agreement item status [{$itemStatus}].");
                }

                $budgetId = null;
                if (! empty($item['plan_budget_public_id'])) {
                    $budget = $this->records->planBudget($request->companyId, (string) $item['plan_budget_public_id']);
                    if ($planId === null || (int) $budget->plan_id !== $planId) {
                        throw new DomainException('Agreement item budget does not belong to the selected plan.');
                    }
                    $budgetId = (int) $budget->id;
                }

                $activeFrom = new DateTimeImmutable((string) ($item['active_from'] ?? $payload['starts_on']));
                $activeUntil = isset($item['active_until']) && $item['active_until'] !== null
                    ? new DateTimeImmutable((string) $item['active_until'])
                    : null;
                if ($activeFrom < $startsOn || ($endsOn !== null && $activeFrom > $endsOn)) {
                    throw new DomainException('Agreement item start date must fall within the service agreement.');
                }
                if ($activeUntil !== null && ($activeUntil < $activeFrom || ($endsOn !== null && $activeUntil > $endsOn))) {
                    throw new DomainException('Agreement item end date must follow its start and remain within the service agreement.');
                }

                $activeFromValue = $activeFrom->format('Y-m-d');
                $activeUntilValue = $activeUntil?->format('Y-m-d');
                $current = $this->db->table('tz_ndis_service_agreement_items')
                    ->where('agreement_id', $agreementId)
                    ->where('support_item_code', $code)
                    ->where('active_from', $activeFromValue)
                    ->whereNull('deleted_at')
                    ->first();
                $itemPublicId = $current?->public_id ?? (string) Str::ulid();

                $unitRate = round((float) ($item['unit_rate'] ?? 0), 4);
                $maximumUnits = isset($item['maximum_units']) ? round((float) $item['maximum_units'], 4) : null;
                $maximumAmount = isset($item['maximum_amount']) ? round((float) $item['maximum_amount'], 4) : null;
                if ($unitRate < 0 || ($maximumUnits !== null && $maximumUnits < 0) || ($maximumAmount !== null && $maximumAmount < 0)) {
                    throw new DomainException('Service agreement item financial limits cannot be negative.');
                }

                $itemValues = [
                    'company_id' => $request->companyId,
                    'agreement_id' => $agreementId,
                    'plan_budget_id' => $budgetId,
                    'category_code' => trim((string) ($item['category_code'] ?? 'uncategorised')),
                    'support_item_code' => $code,
                    'support_item_name' => $name,
                    'unit' => $unit,
                    'unit_rate' => $unitRate,
                    'maximum_units' => $maximumUnits,
                    'maximum_amount' => $maximumAmount,
                    'claim_type' => $claimType,
                    'gst_code' => (string) ($item['gst_code'] ?? 'GST_FREE'),
                    'active_from' => $activeFromValue,
                    'active_until' => $activeUntilValue,
                    'status' => $itemStatus,
                    'metadata' => json_encode((array) ($item['metadata'] ?? []), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ];
                if ($current === null) {
                    $this->db->table('tz_ndis_service_agreement_items')->insert($itemValues + [
                        'public_id' => $itemPublicId,
                        'created_at' => now(),
                    ]);
                } else {
                    $this->db->table('tz_ndis_service_agreement_items')->where('id', (int) $current->id)->update($itemValues);
                }
                $result[] = (string) $itemPublicId;
            }

            return $result;
        }, 3);

        $data = [
            'public_id' => $publicId,
            'participant_public_id' => (string) $participant->public_id,
            'status' => $status,
            'item_public_ids' => $itemPublicIds,
        ];
        return new ActionHandlerResult(
            $data,
            new TypedReference('ndis_service_agreement', $publicId),
            [new PendingDomainEvent('workcore.ndis.agreement.upserted', 1, $data)],
        );
    }
}
