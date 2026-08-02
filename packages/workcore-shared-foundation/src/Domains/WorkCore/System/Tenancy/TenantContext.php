<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Context\TenantContextSnapshot;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use RuntimeException;

final class TenantContext implements TenantContextContract
{
    public function __construct(private ?int $companyId = null, private ?int $userId = null) {}
    public function hasTenant(): bool { return $this->companyId !== null; }
    public function companyId(): int
    {
        if ($this->companyId === null) { throw new RuntimeException('WorkCore tenant context has not been resolved.'); }
        return $this->companyId;
    }
    public function userId(): ?int { return $this->userId; }
    public function set(int $companyId, ?int $userId = null): void { $this->restore(new TenantContextSnapshot($companyId, $userId)); }
    public function restore(TenantContextSnapshot $snapshot): void { $this->companyId = $snapshot->companyId; $this->userId = $snapshot->userId; }
    public function snapshot(): TenantContextSnapshot { return new TenantContextSnapshot($this->companyId(), $this->userId); }
    public function clear(): void { $this->companyId = null; $this->userId = null; }
}
