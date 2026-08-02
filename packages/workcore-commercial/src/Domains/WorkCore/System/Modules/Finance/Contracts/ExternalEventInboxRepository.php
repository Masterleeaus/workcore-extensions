<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\ExternalEventReceipt;
use DateTimeImmutable;

interface ExternalEventInboxRepository
{
    public function claim(string $companyId, string $sourceSystem, string $externalEventId, string $eventType, string $payloadHash, DateTimeImmutable $receivedAt): ExternalEventReceipt;
    public function complete(string $companyId, string $sourceSystem, string $externalEventId, DateTimeImmutable $processedAt): void;
    public function fail(string $companyId, string $sourceSystem, string $externalEventId, string $failureCode): void;
}
