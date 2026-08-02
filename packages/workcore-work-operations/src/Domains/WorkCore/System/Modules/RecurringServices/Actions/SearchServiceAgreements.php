<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\RecurringServices\Actions;
use App\Domains\WorkCore\System\Modules\RecurringServices\Contracts\RecurringServiceRepositoryContract;
final class SearchServiceAgreements { public function __construct(private RecurringServiceRepositoryContract $repository){} public function __invoke(array $filters,int $companyId,int $perPage=25): mixed{return $this->repository->search($companyId,$filters,$perPage);} }
