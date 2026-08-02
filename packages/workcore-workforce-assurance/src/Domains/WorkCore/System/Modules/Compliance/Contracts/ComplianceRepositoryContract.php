<?php declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Compliance\Contracts;
interface ComplianceRepositoryContract {
    public function create(array $data): string;
    public function findById(string $id): ?array;
    public function update(string $id, array $data): bool;
}
