<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Contracts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
interface FleetRepositoryContract
{
    public function search(int $companyId,array $filters=[],int $perPage=25):LengthAwarePaginator;
    public function createVehicle(array $data,int $companyId,int $actorId):array;
    public function recordMileage(string $vehiclePublicId,array $data,int $companyId,int $actorId):array;
    public function assign(string $vehiclePublicId,array $data,int $companyId,int $actorId):array;
    public function returnVehicle(string $assignmentPublicId,array $data,int $companyId,int $actorId):array;
}
