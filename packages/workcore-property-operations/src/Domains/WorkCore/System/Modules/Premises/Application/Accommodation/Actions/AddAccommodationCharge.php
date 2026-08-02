<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\AccommodationActionSupport;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AddAccommodationCharge implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $result = $this->db->transaction(function () use ($request): array {
            $reservation = $this->records->reservation(
                $request->companyId,
                (string) $request->payload['reservation_public_id'],
                true,
            );
            $stayId = null;
            $stayPublicId = trim((string) ($request->payload['stay_public_id'] ?? ''));
            if ($stayPublicId !== '') {
                $stay = $this->records->stay($request->companyId, $stayPublicId, true);
                if ((int) $stay->reservation_id !== (int) $reservation->id) {
                    throw new InvalidArgumentException('Accommodation stay does not belong to the reservation.');
                }
                $stayId = (int) $stay->id;
            }

            $quantity = (float) ($request->payload['quantity'] ?? 1);
            $unitAmount = (float) $request->payload['unit_amount'];
            $taxAmount = (float) ($request->payload['tax_amount'] ?? 0);
            $chargeType = (string) ($request->payload['charge_type'] ?? 'accommodation');
            $calculated = round($quantity * $unitAmount + $taxAmount, 4);
            $totalAmount = array_key_exists('total_amount', $request->payload)
                ? (float) $request->payload['total_amount']
                : $calculated;
            if ($quantity <= 0 || abs($totalAmount - $calculated) > 0.01) {
                throw new DomainException('Accommodation charge total must equal quantity multiplied by unit amount plus tax.');
            }
            if (! in_array($chargeType, ['discount', 'refund'], true) && ($unitAmount < 0 || $totalAmount < 0)) {
                throw new DomainException('Only discount and refund charge types may be negative.');
            }

            $status = (string) ($request->payload['status'] ?? 'posted');
            if (! in_array($status, ['pending', 'posted'], true)) {
                throw new DomainException('A new accommodation charge must be pending or posted.');
            }
            $publicId = (string) Str::ulid();
            $this->db->table('pm_accommodation_charges')->insert([
                'public_id' => $publicId,
                'company_id' => $request->companyId,
                'reservation_id' => (int) $reservation->id,
                'stay_id' => $stayId,
                'agreement_id' => $reservation->agreement_id,
                'charge_type' => $chargeType,
                'description' => trim((string) $request->payload['description']),
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'currency' => strtoupper((string) ($request->payload['currency'] ?? $reservation->currency ?? 'AUD')),
                'status' => $status,
                'occurred_at' => $request->payload['occurred_at'] ?? now(),
                'posted_at' => $status === 'posted' ? now() : null,
                'finance_provider' => $request->payload['finance_provider'] ?? null,
                'finance_external_id' => $request->payload['finance_external_id'] ?? null,
                'finance_reference' => $request->payload['finance_reference'] ?? null,
                'metadata' => json_encode((array) ($request->payload['metadata'] ?? []), JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return [
                'public_id' => $publicId,
                'reservation_public_id' => (string) $reservation->public_id,
                'stay_public_id' => $stayPublicId !== '' ? $stayPublicId : null,
                'charge_type' => $chargeType,
                'description' => trim((string) $request->payload['description']),
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'currency' => strtoupper((string) ($request->payload['currency'] ?? $reservation->currency ?? 'AUD')),
                'status' => $status,
            ];
        }, 3);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('accommodation_charge', (string) $result['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.charge.added', 1, ['charge' => $result])],
        );
    }
}
