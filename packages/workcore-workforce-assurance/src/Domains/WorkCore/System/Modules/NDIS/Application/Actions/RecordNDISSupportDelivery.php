<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\ServiceDeliveryCalculator;
use App\Domains\WorkCore\System\References\TypedReference;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class RecordNDISSupportDelivery implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $agreement = $this->records->agreement($request->companyId, (string) $payload['agreement_public_id']);
        $item = $this->records->agreementItem($request->companyId, (string) $payload['agreement_item_public_id']);

        if ((int) $agreement->participant_id !== (int) $participant->id || (int) $item->agreement_id !== (int) $agreement->id) {
            throw new DomainException('Delivery participant, agreement and agreement item do not align.');
        }
        if ((string) $participant->status !== 'active') {
            throw new DomainException('Support delivery can only be recorded for an active participant.');
        }
        if ((string) $agreement->status !== 'active') {
            throw new DomainException('Support delivery requires an active service agreement.');
        }
        if ((string) $item->status !== 'active') {
            throw new DomainException('Support delivery requires an active service agreement item.');
        }

        $startedAt = isset($payload['started_at']) ? new DateTimeImmutable((string) $payload['started_at']) : null;
        $endedAt = isset($payload['ended_at']) ? new DateTimeImmutable((string) $payload['ended_at']) : null;
        if ($startedAt !== null && $endedAt !== null && $endedAt < $startedAt) {
            throw new DomainException('Delivery end time must not precede its start time.');
        }

        $serviceDate = new DateTimeImmutable((string) ($payload['service_date'] ?? ($startedAt?->format('Y-m-d') ?? date('Y-m-d'))));
        $agreementStarts = new DateTimeImmutable((string) $agreement->starts_on);
        $agreementEnds = $agreement->ends_on !== null ? new DateTimeImmutable((string) $agreement->ends_on) : null;
        if ($serviceDate < $agreementStarts || ($agreementEnds !== null && $serviceDate > $agreementEnds)) {
            throw new DomainException('Support delivery date falls outside the active service agreement.');
        }
        $itemStarts = new DateTimeImmutable((string) $item->active_from);
        $itemEnds = $item->active_until !== null ? new DateTimeImmutable((string) $item->active_until) : null;
        if ($serviceDate < $itemStarts || ($itemEnds !== null && $serviceDate > $itemEnds)) {
            throw new DomainException('Service agreement item is not effective on the delivery date.');
        }

        $scheduledMinutes = max(0, (int) ($payload['scheduled_minutes'] ?? 0));
        $deliveredMinutes = max(0, (int) ($payload['delivered_minutes'] ?? (
            $startedAt && $endedAt ? intdiv($endedAt->getTimestamp() - $startedAt->getTimestamp(), 60) : 0
        )));
        $outcome = (string) ($payload['outcome'] ?? 'delivered');
        $cancellationTerms = $this->records->decode($agreement->cancellation_terms ?? null);
        $claimableMinutes = ServiceDeliveryCalculator::claimableMinutes(
            $outcome === 'delivered' ? $deliveredMinutes : $scheduledMinutes,
            $outcome,
            (int) ($payload['cancellation_claimable_minutes'] ?? $cancellationTerms['claimable_minutes'] ?? 0),
        );

        $unit = (string) $item->unit;
        if (isset($payload['unit']) && (string) $payload['unit'] !== $unit) {
            throw new DomainException('Delivery unit must match the selected service agreement item.');
        }
        $explicitQuantity = isset($payload['quantity']) ? round((float) $payload['quantity'], 4) : null;
        if (in_array($unit, ['hour', 'minute'], true)) {
            $quantity = ServiceDeliveryCalculator::quantity($claimableMinutes, $unit);
            if ($explicitQuantity !== null && abs($explicitQuantity - $quantity) > 0.0001) {
                throw new DomainException('Time-based delivery quantity must match the recorded duration.');
            }
        } else {
            $quantity = ServiceDeliveryCalculator::quantity($claimableMinutes, $unit, $explicitQuantity);
        }
        $rate = round((float) $item->unit_rate, 4);
        $amount = ServiceDeliveryCalculator::amount($quantity, $rate);

        $publicId = trim((string) ($payload['delivery_public_id'] ?? '')) ?: (string) Str::ulid();
        $existing = $this->db->table('tz_ndis_support_deliveries')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();

        $usageQuery = $this->db->table('tz_ndis_support_deliveries')
            ->where('company_id', $request->companyId)
            ->where('agreement_item_id', (int) $item->id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'voided');
        if ($existing !== null) {
            $usageQuery->where('id', '!=', (int) $existing->id);
        }
        $usedQuantity = round((float) (clone $usageQuery)->sum('quantity'), 4);
        $usedAmount = round((float) (clone $usageQuery)->sum('claimable_amount'), 4);
        if ($item->maximum_units !== null && $usedQuantity + $quantity > round((float) $item->maximum_units, 4)) {
            throw new DomainException('Service agreement item maximum units would be exceeded.');
        }
        if ($item->maximum_amount !== null && $usedAmount + $amount > round((float) $item->maximum_amount, 4)) {
            throw new DomainException('Service agreement item maximum amount would be exceeded.');
        }

        $goalId = null;
        if (! empty($payload['goal_public_id'])) {
            $goal = $this->records->goal($request->companyId, (string) $payload['goal_public_id']);
            if ((int) $goal->participant_id !== (int) $participant->id) {
                throw new DomainException('Delivery goal belongs to another participant.');
            }
            $goalId = (int) $goal->id;
        }

        $approved = ! empty($payload['approved']) || (string) ($payload['status'] ?? '') === 'approved';
        $status = $approved ? 'approved' : (string) ($payload['status'] ?? 'recorded');
        if (! in_array($status, ['recorded', 'approved', 'voided'], true)) {
            throw new DomainException("Unsupported support-delivery status [{$status}].");
        }

        $values = [
            'company_id' => $request->companyId,
            'participant_id' => (int) $participant->id,
            'plan_id' => $agreement->plan_id,
            'agreement_id' => (int) $agreement->id,
            'agreement_item_id' => (int) $item->id,
            'goal_id' => $goalId,
            'worker_id' => $this->records->workerId($request->companyId, $payload['worker_public_id'] ?? null),
            'appointment_id' => $this->records->appointmentId($request->companyId, $payload['appointment_public_id'] ?? null),
            'work_order_id' => $this->records->workOrderId($request->companyId, $payload['work_order_public_id'] ?? null),
            'premise_id' => $this->records->premiseId($request->companyId, $payload['premise_public_id'] ?? null),
            'occupancy_id' => $this->records->occupancyId($request->companyId, $payload['occupancy_public_id'] ?? null),
            'service_date' => $serviceDate->format('Y-m-d'),
            'started_at' => $payload['started_at'] ?? null,
            'ended_at' => $payload['ended_at'] ?? null,
            'scheduled_minutes' => $scheduledMinutes,
            'delivered_minutes' => $deliveredMinutes,
            'outcome' => $outcome,
            'unit' => $unit,
            'quantity' => $quantity,
            'unit_rate_snapshot' => $rate,
            'claimable_amount' => $amount,
            'travel_minutes' => max(0, (int) ($payload['travel_minutes'] ?? 0)),
            'travel_kilometres' => round(max(0, (float) ($payload['travel_kilometres'] ?? 0)), 4),
            'travel_amount' => round(max(0, (float) ($payload['travel_amount'] ?? 0)), 4),
            'status' => $status,
            'cancellation_reason' => $payload['cancellation_reason'] ?? null,
            'delivery_notes' => $payload['delivery_notes'] ?? null,
            'evidence' => json_encode((array) ($payload['evidence'] ?? []), JSON_THROW_ON_ERROR),
            'metadata' => json_encode((array) ($payload['metadata'] ?? []), JSON_THROW_ON_ERROR),
            'recorded_by_user_id' => $request->actorId,
            'approved_by_user_id' => $approved ? $request->actorId : null,
            'approved_at' => $approved ? now() : null,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            $this->db->table('tz_ndis_support_deliveries')->insert($values + [
                'public_id' => $publicId,
                'created_at' => now(),
            ]);
        } else {
            if ((int) $existing->participant_id !== (int) $participant->id) {
                throw new DomainException('Delivery belongs to another participant.');
            }
            if ((string) $existing->status === 'approved' && $status !== 'approved') {
                throw new DomainException('An approved delivery cannot be returned to an unapproved state.');
            }
            $this->db->table('tz_ndis_support_deliveries')->where('id', (int) $existing->id)->update($values);
        }

        $data = [
            'public_id' => $publicId,
            'participant_public_id' => (string) $participant->public_id,
            'outcome' => $outcome,
            'status' => $status,
            'quantity' => $quantity,
            'claimable_amount' => $amount,
            'rate_source' => 'service_agreement_item',
        ];
        return new ActionHandlerResult(
            $data,
            new TypedReference('ndis_support_delivery', $publicId),
            [new PendingDomainEvent('workcore.ndis.delivery.recorded', 1, $data)],
        );
    }
}
