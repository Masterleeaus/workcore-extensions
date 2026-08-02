<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UpsertTradeLicence implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private CompanyVerticalProfileResolver $profiles,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $verticalKey = (string) $request->payload['vertical_key'];
        if (! in_array($verticalKey, ['plumbing', 'electrical'], true)) {
            throw new InvalidArgumentException('Trade licences are currently supported for plumbing and electrical verticals.');
        }
        if (! $this->profiles->resolve($request->companyId)->hasPack($verticalKey)) {
            throw new DomainException("Vertical [{$verticalKey}] is not active for the company.");
        }

        $worker = $this->db->table('tz_workers')
            ->where('company_id', $request->companyId)
            ->where('public_id', (string) $request->payload['worker_public_id'])
            ->whereNull('deleted_at')
            ->first(['id', 'public_id']);
        if ($worker === null) {
            throw new InvalidArgumentException('Worker does not belong to the active company.');
        }

        $jurisdiction = strtoupper(trim((string) ($request->payload['jurisdiction'] ?? 'AU-VIC')));
        $licenceNumber = trim((string) $request->payload['licence_number']);
        $existing = $this->db->table('tz_trade_licences')
            ->where('company_id', $request->companyId)
            ->where('licence_number', $licenceNumber)
            ->where('jurisdiction', $jurisdiction)
            ->first();

        if ($existing !== null && (
            (int) $existing->worker_id !== (int) $worker->id
            || (string) $existing->vertical_key !== $verticalKey
        )) {
            throw new DomainException('An existing trade licence cannot be reassigned to another worker or trade vertical.');
        }

        $now = now();
        $businessLineId = $this->businessLineId(
            request: $request,
            existingId: $existing?->business_line_id !== null ? (int) $existing->business_line_id : null,
        );
        $expiresOn = $this->preserveOptional($request->payload, 'expires_on', $existing?->expires_on);
        $status = (string) $this->preserveOptional($request->payload, 'status', $existing?->status ?? 'valid');
        if (is_string($expiresOn) && $expiresOn < $now->toDateString() && $status === 'valid') {
            $status = 'expired';
        }

        $verifiedSpecified = array_key_exists('verified', $request->payload);
        $verified = (bool) ($request->payload['verified'] ?? false);
        $values = [
            'company_id' => $request->companyId,
            'worker_id' => (int) $worker->id,
            'business_line_id' => $businessLineId,
            'vertical_key' => $verticalKey,
            'licence_type' => trim((string) $request->payload['licence_type']),
            'licence_class' => $this->preserveOptional($request->payload, 'licence_class', $existing?->licence_class),
            'licence_number' => $licenceNumber,
            'jurisdiction' => $jurisdiction,
            'issuer' => $this->preserveOptional($request->payload, 'issuer', $existing?->issuer),
            'status' => $status,
            'issued_on' => $this->preserveOptional($request->payload, 'issued_on', $existing?->issued_on),
            'expires_on' => $expiresOn,
            'verified_at' => $verifiedSpecified ? ($verified ? $now : null) : $existing?->verified_at,
            'verified_by_user_id' => $verifiedSpecified ? ($verified ? $request->actorId : null) : $existing?->verified_by_user_id,
            'conditions' => array_key_exists('conditions', $request->payload)
                ? json_encode((array) $request->payload['conditions'], JSON_THROW_ON_ERROR)
                : $existing?->conditions,
            'document_path' => $this->preserveOptional($request->payload, 'document_path', $existing?->document_path),
            'notes' => $this->preserveOptional($request->payload, 'notes', $existing?->notes),
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $publicId = $existing !== null ? (string) $existing->public_id : (string) Str::ulid();
        if ($existing !== null) {
            $this->db->table('tz_trade_licences')->where('id', (int) $existing->id)->update($values);
        } else {
            $this->db->table('tz_trade_licences')->insert($values + [
                'public_id' => $publicId,
                'created_at' => $now,
            ]);
        }

        $record = (array) $this->db->table('tz_trade_licences')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->first();
        $record['conditions'] = $this->decode($record['conditions'] ?? null);

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('trade_licence', $publicId),
            events: [new PendingDomainEvent('workcore.trade_licence.upserted', 1, ['trade_licence' => $record])],
        );
    }

    private function businessLineId(ActionRequest $request, ?int $existingId): ?int
    {
        if (! array_key_exists('business_line_public_id', $request->payload)) {
            return $existingId;
        }

        $publicId = trim((string) ($request->payload['business_line_public_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }

        $line = $this->db->table('tz_business_lines as bl')
            ->leftJoin('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
            ->where('bl.company_id', $request->companyId)
            ->where('bl.public_id', $publicId)
            ->where('bl.status', 'active')
            ->where('cv.status', 'active')
            ->whereNull('bl.deleted_at')
            ->first(['bl.id', 'cv.vertical_key']);
        if ($line === null || (string) $line->vertical_key !== (string) $request->payload['vertical_key']) {
            throw new InvalidArgumentException('Business line must belong to the selected trade vertical.');
        }

        return (int) $line->id;
    }

    private function preserveOptional(array $payload, string $key, mixed $existing): mixed
    {
        return array_key_exists($key, $payload) ? $payload[$key] : $existing;
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
