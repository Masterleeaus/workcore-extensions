<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Forms\Actions;
use App\Domains\WorkCore\System\Modules\Forms\Contracts\FormRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchFormTemplates
{
    public function __construct(private FormRepositoryContract $repository) {}
    public function execute(array $filters = [], int $perPage = 25): LengthAwarePaginator { return $this->repository->searchTemplates((int) workcore_tenant()->companyId(), $filters, $perPage); }
}
