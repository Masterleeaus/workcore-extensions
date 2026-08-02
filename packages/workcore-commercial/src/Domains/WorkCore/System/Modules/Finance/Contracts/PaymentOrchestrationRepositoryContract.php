<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;
interface PaymentOrchestrationRepositoryContract
{
    public function upsertProviderConnection(array $data, int $companyId, int $actorId): array;
    public function createSession(array $data, int $companyId, int $actorId): array;
    public function recordAttempt(array $data, int $companyId, int $actorId): array;
    public function paymentSession(string $id, int $companyId): array;
    public function summary(int $companyId): array;
}
