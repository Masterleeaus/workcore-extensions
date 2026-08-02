<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Finance\Actions;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentOrchestrationRepositoryContract;
use InvalidArgumentException;
final class GetPaymentSession{public function __construct(private PaymentOrchestrationRepositoryContract $repository){}public function __invoke(array $filters,int $companyId,int $perPage=25):array{$id=trim((string)($filters['session_id']??$filters['payment_session_id']??''));if($id==='')throw new InvalidArgumentException('session_id is required.');return $this->repository->paymentSession($id,$companyId);}}
