<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

use DomainException;
use InvalidArgumentException;

final class OfflineAttendanceBatch
{
    private OfflineAttendanceBatchState $state = OfflineAttendanceBatchState::Open;
    /** @var array<string,array{type:string,payload_hash:string}> */
    private array $events = [];

    public function __construct(public readonly string $id, public readonly string $companyId, public readonly string $workerId, public readonly string $deviceId)
    {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '' || trim($deviceId) === '') throw new InvalidArgumentException('Offline batch identity is required.');
    }
    public function state(): OfflineAttendanceBatchState { return $this->state; }
    public function eventCount(): int { return count($this->events); }
    public function addEvent(string $eventId, string $type, string $payloadHash): void
    {
        if ($this->state !== OfflineAttendanceBatchState::Open) throw new DomainException('Offline events can only be added to open batches.');
        if (trim($eventId) === '' || trim($type) === '' || trim($payloadHash) === '') throw new InvalidArgumentException('Offline event identity, type and payload hash are required.');
        if (isset($this->events[$eventId])) throw new DomainException('Offline event identifiers are idempotent and cannot be repeated.');
        $this->events[$eventId] = ['type' => $type, 'payload_hash' => $payloadHash];
    }
    public function seal(): void
    {
        if ($this->state !== OfflineAttendanceBatchState::Open || $this->events === []) throw new DomainException('A non-empty open batch is required before sealing.');
        $this->state = OfflineAttendanceBatchState::Sealed;
    }
}
