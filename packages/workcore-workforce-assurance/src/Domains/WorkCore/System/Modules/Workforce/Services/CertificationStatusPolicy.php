<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Services;
use DateTimeImmutable;
final class CertificationStatusPolicy
{
    public function resolve(?string $expiresOn, string $declaredStatus='valid', ?DateTimeImmutable $today=null): string
    {
        if (in_array($declaredStatus, ['suspended','revoked'], true)) return $declaredStatus;
        if ($expiresOn === null || $expiresOn === '') return $declaredStatus;
        $today ??= new DateTimeImmutable('today');
        return new DateTimeImmutable($expiresOn) < $today ? 'expired' : $declaredStatus;
    }
}
