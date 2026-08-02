<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts;

use App\Domains\WorkCore\System\Context\TenantContextSnapshot;

interface TenantContextContract
{
    public function hasTenant(): bool;
    public function companyId(): int;
    public function userId(): ?int;
    public function set(int $companyId, ?int $userId = null): void;
    public function restore(TenantContextSnapshot $snapshot): void;
    public function snapshot(): TenantContextSnapshot;
    public function clear(): void;
}
