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

final class UpsertAccommodationRatePlan implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $result = $this->db->transaction(function () use ($request): array {
            $payload = $request->payload;
            $publicId = trim((string) ($payload['rate_plan_public_id'] ?? ''));
            $existing = null;
            if ($publicId !== '') {
                $existing = $this->db->table('pm_accommodation_rate_plans')
                    ->where('company_id', $request->companyId)->where('public_id', $publicId)
                    ->whereNull('deleted_at')->lockForUpdate()->first();
                if ($existing === null) throw new DomainException('Accommodation rate plan does not belong to the active company.');
            } else {
                $publicId = (string) Str::ulid();
            }

            $code = trim((string) $payload['code']);
            $duplicate = $this->db->table('pm_accommodation_rate_plans')
                ->where('company_id', $request->companyId)->where('code', $code)->whereNull('deleted_at')
                ->when($existing, fn ($query) => $query->where('id', '!=', (int) $existing->id))
                ->exists();
            if ($duplicate) throw new DomainException('Accommodation rate-plan code is already in use.');

            $premiseId = null;
            $propertyPublicId = trim((string) ($payload['property_public_id'] ?? ''));
            if ($propertyPublicId !== '') {
                $premiseId = (int) $this->records->property($request->companyId, $propertyPublicId)->id;
            } elseif ($existing?->premise_id !== null) {
                $premiseId = (int) $existing->premise_id;
            }
            $businessLineId = array_key_exists('business_line_public_id', $payload)
                ? $this->records->businessLineId($request->companyId, $payload['business_line_public_id'])
                : ($existing?->business_line_id !== null ? (int) $existing->business_line_id : null);

            $minimumNights = (int) ($payload['minimum_nights'] ?? $existing?->minimum_nights ?? 1);
            $maximumNights = array_key_exists('maximum_nights', $payload)
                ? ($payload['maximum_nights'] === null ? null : (int) $payload['maximum_nights'])
                : ($existing?->maximum_nights !== null ? (int) $existing->maximum_nights : null);
            if ($minimumNights < 1 || ($maximumNights !== null && $maximumNights < $minimumNights)) {
                throw new DomainException('Rate-plan night limits are invalid.');
            }
            $baseAmount = (float) ($payload['base_amount'] ?? $existing?->base_amount ?? 0);
            if ($baseAmount < 0) throw new DomainException('Rate-plan base amount cannot be negative.');

            $values = [
                'public_id' => $publicId,
                'company_id' => $request->companyId,
                'premise_id' => $premiseId,
                'business_line_id' => $businessLineId,
                'code' => $code,
                'name' => trim((string) $payload['name']),
                'pricing_basis' => (string) ($payload['pricing_basis'] ?? $existing?->pricing_basis ?? 'night'),
                'currency' => strtoupper((string) ($payload['currency'] ?? $existing?->currency ?? 'AUD')),
                'base_amount' => $baseAmount,
                'minimum_nights' => $minimumNights,
                'maximum_nights' => $maximumNights,
                'active' => array_key_exists('active', $payload) ? (bool) $payload['active'] : (bool) ($existing?->active ?? true),
                'restrictions' => json_encode((array) ($payload['restrictions'] ?? $this->records->decode($existing?->restrictions)), JSON_THROW_ON_ERROR),
                'cancellation_policy' => json_encode((array) ($payload['cancellation_policy'] ?? $this->records->decode($existing?->cancellation_policy)), JSON_THROW_ON_ERROR),
                'metadata' => json_encode(array_replace(
                    $this->records->decode($existing?->metadata),
                    (array) ($payload['metadata'] ?? []),
                ), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ];
            if ($existing === null) {
                $this->db->table('pm_accommodation_rate_plans')->insert($values + ['created_at' => now()]);
            } else {
                $this->db->table('pm_accommodation_rate_plans')->where('id', (int) $existing->id)->update($values);
            }

            return [
                'public_id' => $publicId,
                'property_public_id' => $premiseId === null ? null : (string) $this->db->table('pm_properties')->where('id', $premiseId)->value('public_id'),
                'business_line_public_id' => $businessLineId === null ? null : (string) $this->db->table('tz_business_lines')->where('id', $businessLineId)->value('public_id'),
                'code' => $code,
                'name' => $values['name'],
                'pricing_basis' => $values['pricing_basis'],
                'currency' => $values['currency'],
                'base_amount' => $baseAmount,
                'minimum_nights' => $minimumNights,
                'maximum_nights' => $maximumNights,
                'active' => $values['active'],
                'restrictions' => $this->records->decode($values['restrictions']),
                'cancellation_policy' => $this->records->decode($values['cancellation_policy']),
                'created' => $existing === null,
            ];
        }, 3);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('accommodation_rate_plan', (string) $result['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.rate_plan.upserted', 1, ['rate_plan' => $result])],
        );
    }
}
