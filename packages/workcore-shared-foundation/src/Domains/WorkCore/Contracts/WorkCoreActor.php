<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\Contracts;
interface WorkCoreActor {
 public function getActorId(): int|string;
 public function getActiveCompanyId(): int|string|null;
 public function getMembershipId(): int|string|null;
 public function getLocale(): ?string;
 public function getTimeZone(): ?string;
}
