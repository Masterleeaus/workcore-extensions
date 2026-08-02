<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Catalogue\Actions;
use App\Domains\WorkCore\System\Modules\Catalogue\Contracts\ServiceCatalogueRepositoryContract;
final class SearchServices{public function __construct(private ServiceCatalogueRepositoryContract $repository){} public function execute(?string $q=null,?string $category=null,int $perPage=25){return $this->repository->searchServices(workcore_tenant()->companyId(),$q,$category,$perPage);}}
