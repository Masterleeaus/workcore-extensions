<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
interface RepairRepositoryContract
{
    public function search(int $companyId,array $filters=[],int $perPage=25):LengthAwarePaginator;
    public function createTemplate(array $data,int $companyId):array;
    public function report(array $data,int $companyId,int $actorId):array;
    public function start(string $repairPublicId,array $data,int $companyId,int $actorId):array;
    public function complete(string $repairPublicId,array $data,int $companyId,int $actorId):array;
}
