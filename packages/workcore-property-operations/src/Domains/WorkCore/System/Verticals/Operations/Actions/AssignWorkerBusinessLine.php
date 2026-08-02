<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AssignWorkerBusinessLine implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $worker = $this->db->table('tz_workers')
            ->where('company_id', $request->companyId)
            ->where('public_id', (string) $request->payload['worker_public_id'])
            ->whereNull('deleted_at')
            ->first(['id', 'public_id', 'display_name', 'employment_status']);
        if ($worker === null) {
            throw new InvalidArgumentException('Worker does not belong to the active company.');
        }

        $line = $this->db->table('tz_business_lines as bl')
            ->leftJoin('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
            ->where('bl.company_id', $request->companyId)
            ->where('bl.public_id', (string) $request->payload['business_line_public_id'])
            ->where('bl.status', 'active')
            ->where('cv.status', 'active')
            ->whereNull('bl.deleted_at')
            ->first(['bl.id', 'bl.public_id', 'bl.key', 'bl.name']);
        if ($line === null) {
            throw new InvalidArgumentException('Business line does not belong to the active company or is inactive.');
        }

        $record = $this->db->transaction(function () use ($request, $worker, $line): array {
            $this->db->table('tz_workers')
                ->where('company_id', $request->companyId)
                ->where('id', (int) $worker->id)
                ->lockForUpdate()
                ->value('id');

            $existing = $this->db->table('tz_worker_business_lines')
                ->where('worker_id', (int) $worker->id)
                ->where('business_line_id', (int) $line->id)
                ->lockForUpdate()
                ->first();

            $isPrimary = array_key_exists('is_primary', $request->payload)
                ? (bool) $request->payload['is_primary']
                : (bool) ($existing?->is_primary ?? false);
            $active = array_key_exists('active', $request->payload)
                ? (bool) $request->payload['active']
                : (bool) ($existing?->active ?? true);
            if ($isPrimary && ! $active) {
                throw new InvalidArgumentException('An inactive worker assignment cannot be primary.');
            }

            if ($isPrimary) {
                $this->db->table('tz_worker_business_lines')
                    ->where('company_id', $request->companyId)
                    ->where('worker_id', (int) $worker->id)
                    ->where('business_line_id', '!=', (int) $line->id)
                    ->where('is_primary', true)
                    ->lockForUpdate()
                    ->update(['is_primary' => false, 'updated_at' => now()]);
            }

            $publicId = $existing === null ? (string) Str::ulid() : (string) $existing->public_id;
            $values = [
                'company_id' => $request->companyId,
                'worker_id' => (int) $worker->id,
                'business_line_id' => (int) $line->id,
                'role' => trim((string) $this->preserveOptional($request->payload, 'role', $existing?->role ?? 'worker')),
                'is_primary' => $isPrimary,
                'active' => $active,
                'assigned_at' => $active
                    ? ($existing !== null ? ($existing->assigned_at ?? now()) : now())
                    : $existing?->assigned_at,
                'assigned_by_user_id' => $request->actorId,
                'settings' => json_encode(
                    (array) $this->preserveOptional($request->payload, 'settings', $this->decode($existing?->settings)),
                    JSON_THROW_ON_ERROR,
                ),
                'updated_at' => now(),
            ];
            if ($values['role'] === '') {
                throw new InvalidArgumentException('Worker business-line role cannot be empty.');
            }

            if ($existing === null) {
                $this->db->table('tz_worker_business_lines')->insert($values + [
                    'public_id' => $publicId,
                    'created_at' => now(),
                ]);
            } else {
                $this->db->table('tz_worker_business_lines')->where('id', (int) $existing->id)->update($values);
            }

            return [
                'public_id' => $publicId,
                'worker_public_id' => (string) $worker->public_id,
                'worker_name' => (string) $worker->display_name,
                'business_line_public_id' => (string) $line->public_id,
                'business_line_key' => (string) $line->key,
                'business_line_name' => (string) $line->name,
                'role' => $values['role'],
                'is_primary' => $isPrimary,
                'active' => $active,
                'settings' => $this->decode($values['settings']),
            ];
        }, 3);

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('worker_business_line', (string) $record['public_id']),
            events: [new PendingDomainEvent('workcore.worker_business_line.assigned', 1, ['assignment' => $record])],
        );
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
