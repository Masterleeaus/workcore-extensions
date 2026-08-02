<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Compliance\Actions;
use App\Domains\WorkCore\System\Modules\Compliance\Contracts\ComplianceRepositoryContract;
final class SearchCompliance { public function __construct(private ComplianceRepositoryContract $repository){} public function __invoke(array $filters,int $companyId,int $perPage=25): mixed{return $this->repository->search($companyId,$filters,$perPage);} }
