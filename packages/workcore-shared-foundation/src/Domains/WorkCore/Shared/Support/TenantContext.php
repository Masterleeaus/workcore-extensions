<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Shared\Support;
use RuntimeException;
final class TenantContext {
 public function __construct(private int|string|null $companyId=null){}
 public function establish(int|string $companyId): void {$this->companyId=$companyId;}
 public function clear(): void {$this->companyId=null;}
 public function companyId(): int|string {return $this->companyId ?? throw new RuntimeException('WorkCore company context is not established.');}
}
