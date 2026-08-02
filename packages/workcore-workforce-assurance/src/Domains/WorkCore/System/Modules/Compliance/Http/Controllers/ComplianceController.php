<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Compliance\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\ComplianceObligationResource;
use App\Domains\WorkCore\System\Modules\Compliance\Actions\SearchCompliance;
use App\Domains\WorkCore\System\Modules\Compliance\Http\Requests\{ComplianceStatusRequest,CorrectiveActionRequest,CorrectiveWorkOrderRequest,RecordCertificateRequest,RecordHazardRequest,RecordIncidentRequest,SafetyStatusRequest,StoreComplianceObligationRequest,StoreComplianceRequirementRequest};
use Illuminate\Http\{JsonResponse,Request};

final class ComplianceController
{
    public function index(Request $request, SearchCompliance $search, ApiResponseFactory $api): JsonResponse
    {
        $tenant=workcore_tenant();
        return $api->paginated($search($request->query(),(int)$tenant->companyId(),(int)$request->query('per_page',25)),ComplianceObligationResource::make(...));
    }
    public function requirement(StoreComplianceRequirementRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.compliance.requirement.create',$r->validated(),$r,$d,$api);}
    public function obligation(StoreComplianceObligationRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.compliance.obligation.create',$r->validated(),$r,$d,$api);}
    public function obligationStatus(ComplianceStatusRequest $r,string $obligation,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.compliance.obligation.change_status',['obligation_public_id'=>$obligation]+$r->validated(),$r,$d,$api);}
    public function certificate(RecordCertificateRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.compliance.certificate.record',$r->validated(),$r,$d,$api);}
    public function hazard(RecordHazardRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.safety.hazard.record',$r->validated(),$r,$d,$api);}
    public function hazardStatus(SafetyStatusRequest $r,string $hazard,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.safety.hazard.change_status',['hazard_public_id'=>$hazard]+$r->validated(),$r,$d,$api);}
    public function incident(RecordIncidentRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.safety.incident.record',$r->validated(),$r,$d,$api);}
    public function incidentStatus(SafetyStatusRequest $r,string $incident,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.safety.incident.change_status',['incident_public_id'=>$incident]+$r->validated(),$r,$d,$api);}
    public function correctiveAction(CorrectiveActionRequest $r,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.compliance.corrective_action.create',$r->validated(),$r,$d,$api);}
    public function correctiveWorkOrder(CorrectiveWorkOrderRequest $r,string $correctiveAction,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse{return $this->dispatch('workcore.compliance.corrective_action.convert_to_work_order',['corrective_action_public_id'=>$correctiveAction]+$r->validated(),$r,$d,$api);}
    private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api): JsonResponse
    {
        $tenant=workcore_tenant();
        $idempotency=trim((string)$request->header('Idempotency-Key'));
        abort_if($idempotency==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($key,$payload,(int)$tenant->companyId(),(int)$tenant->userId(),$idempotency,$request->header('X-WorkCore-Confirmation'),'api'));
        return $api->action($result);
    }
}
