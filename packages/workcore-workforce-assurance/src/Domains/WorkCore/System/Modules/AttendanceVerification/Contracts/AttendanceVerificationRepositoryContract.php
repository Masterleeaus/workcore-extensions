<?php declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Contracts;
interface AttendanceVerificationRepositoryContract {
    public function create(array $data): string;
    public function findById(string $id): ?array;
    public function update(string $id, array $data): bool;
}
