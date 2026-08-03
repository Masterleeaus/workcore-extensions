<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts;

use App\Domains\WorkCore\System\Context\OperationContextSnapshot;

interface OperationContextContract
{
    public function hasContext(): bool;
    public function companyId(): int;
    public function actorId(): ?int;
    public function actorSubject(): string;
    public function workerId(): ?int;
    public function branchId(): ?int;
    public function territoryId(): ?int;
    public function deviceId(): ?string;
    public function authenticationAssurance(): string;
    public function securityRevision(): int;
    public function membershipRevision(): int;
    public function correlationId(): string;
    public function causationId(): ?string;
    public function locale(): string;
    public function timezone(): string;
    public function set(
        int $companyId,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        ?string $locale = null,
        ?string $timezone = null,
        ?string $actorSubject = null,
        ?int $workerId = null,
        ?int $branchId = null,
        ?int $territoryId = null,
        ?string $deviceId = null,
        string $authenticationAssurance = 'authenticated',
        int $securityRevision = 0,
        int $membershipRevision = 0,
    ): void;
    public function restore(OperationContextSnapshot $snapshot): void;
    public function snapshot(): OperationContextSnapshot;
    public function clear(): void;
}
