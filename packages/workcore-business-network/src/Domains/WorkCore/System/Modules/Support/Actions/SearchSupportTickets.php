<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Actions;
use App\Domains\WorkCore\System\Modules\Support\Contracts\SupportTicketRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
final class SearchSupportTickets
{
    public function __construct(private SupportTicketRepositoryContract $repository) {}
    public function execute(array $filters=[],int $perPage=25): LengthAwarePaginator
    {
        $tenant=workcore_tenant();
        return $this->repository->search((int)$tenant->companyId(),$filters,$perPage);
    }
}
