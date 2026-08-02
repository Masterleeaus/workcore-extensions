<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Reviews\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
interface ReviewRepositoryContract
{
    public function search(int $companyId,array $filters=[],int $perPage=25): LengthAwarePaginator;
    public function createRequest(array $data,int $companyId,int $actorId): array;
    public function recordReview(array $data,int $companyId,int $actorId): array;
    public function publish(string $publicId,array $data,int $companyId,int $actorId): array;
    public function createRecovery(string $reviewPublicId,array $data,int $companyId,int $actorId): array;
    public function requestRebooking(array $data,int $companyId,int $actorId): array;
}
