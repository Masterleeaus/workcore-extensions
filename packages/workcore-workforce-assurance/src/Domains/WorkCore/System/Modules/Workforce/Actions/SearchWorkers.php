<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Actions;
use App\Domains\WorkCore\System\Modules\Workforce\Contracts\WorkerRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchWorkers
{
 public function __construct(private WorkerRepositoryContract $repository){}
 public function execute(?string $query=null,?string $status=null,?string $skillPublicId=null,int $perPage=25): LengthAwarePaginator
 { $t=workcore_tenant(); return $this->repository->search((int)$t->companyId(),$query,$status,$skillPublicId,$perPage); }
}
