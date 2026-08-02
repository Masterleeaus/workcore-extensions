<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Actions;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Contracts\KnowledgeBaseRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchKnowledgeArticles
{
    public function __construct(private KnowledgeBaseRepositoryContract $repository) {}
    public function execute(array $filters=[],int $perPage=25): LengthAwarePaginator
    {
        $tenant=workcore_tenant();return $this->repository->search((int)$tenant->companyId(),$filters,$perPage);
    }
}
