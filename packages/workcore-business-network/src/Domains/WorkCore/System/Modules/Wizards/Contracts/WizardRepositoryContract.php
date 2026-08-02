<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Wizards\Contracts;

interface WizardRepositoryContract
{
    public function createDefinition(array $definition, int $companyId, int $actorId): array;
    public function publishDefinition(string $publicId, int $companyId, int $actorId): array;
    public function startRun(array $definition, array $data, int $companyId, int $actorId): array;
    public function saveAnswer(string $runPublicId, array $question, mixed $value, array $provenance, int $companyId, int $actorId): array;
    public function approveSection(string $runPublicId, string $sectionKey, array $summary, int $companyId, int $actorId): array;
    public function changeStatus(string $runPublicId, string $status, int $companyId, int $actorId): array;
    public function run(string $runPublicId, int $companyId): array;
    public function answers(string $runPublicId, int $companyId): array;
    public function approvedSections(string $runPublicId, int $companyId): array;
}
