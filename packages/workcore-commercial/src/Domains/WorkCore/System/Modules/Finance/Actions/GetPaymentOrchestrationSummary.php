<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Finance\Actions;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentOrchestrationRepositoryContract;
final class GetPaymentOrchestrationSummary{public function __construct(private PaymentOrchestrationRepositoryContract $repository){}public function __invoke(array $filters,int $companyId,int $perPage=25):array{return $this->repository->summary($companyId);}}
