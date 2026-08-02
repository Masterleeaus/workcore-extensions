<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
interface WorkerRepositoryContract
{
    public function search(int $companyId, ?string $query=null, ?string $status=null, ?string $skillPublicId=null, int $perPage=25): LengthAwarePaginator;
    public function createWorker(array $data,int $companyId,int $actorId): array;
    public function createSkill(array $data,int $companyId,int $actorId): array;
    public function assignSkill(string $workerPublicId,array $data,int $companyId,int $actorId): array;
    public function addCertification(string $workerPublicId,array $data,int $companyId,int $actorId): array;
}
