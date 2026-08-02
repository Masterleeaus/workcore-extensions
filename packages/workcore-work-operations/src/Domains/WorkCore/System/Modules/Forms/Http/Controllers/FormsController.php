<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Forms\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\{FormSubmissionResource,FormTemplateResource};
use App\Domains\WorkCore\System\Modules\Forms\Actions\{SearchFormSubmissions,SearchFormTemplates};
use App\Domains\WorkCore\System\Modules\Forms\Http\Requests\{ConvertFormFailureRequest,SaveFormResponseRequest,StartFormSubmissionRequest,StoreFormTemplateRequest,SubmitFormSubmissionRequest};
use Illuminate\Http\{JsonResponse,Request};
final class FormsController
{
    public function templates(Request $request, SearchFormTemplates $search, ApiResponseFactory $api): JsonResponse { return $api->paginated($search->execute($request->only(['q','form_type','status','applies_to']), $request->integer('per_page',25)), FormTemplateResource::make(...)); }
    public function submissions(Request $request, SearchFormSubmissions $search, ApiResponseFactory $api): JsonResponse { return $api->paginated($search->execute($request->only(['status','subject_type','premises_public_id','work_order_public_id','asset_public_id']), $request->integer('per_page',25)), FormSubmissionResource::make(...)); }
    public function storeTemplate(StoreFormTemplateRequest $request, BusinessActionDispatcher $d, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.form_template.create',$request->validated(),$request,$d,$api); }
    public function publishTemplate(string $template, Request $request, BusinessActionDispatcher $d, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.form_template.publish',['template_public_id'=>$template],$request,$d,$api); }
    public function start(StartFormSubmissionRequest $request, BusinessActionDispatcher $d, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.form_submission.start',$request->validated(),$request,$d,$api); }
    public function saveResponse(string $submission, SaveFormResponseRequest $request, BusinessActionDispatcher $d, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.form_response.save',['submission_public_id'=>$submission]+$request->validated(),$request,$d,$api); }
    public function submit(string $submission, SubmitFormSubmissionRequest $request, BusinessActionDispatcher $d, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.form_submission.submit',['submission_public_id'=>$submission]+$request->validated(),$request,$d,$api); }
    public function convertFailure(string $failure, ConvertFormFailureRequest $request, BusinessActionDispatcher $d, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.form_failure.convert_to_work_order',['failure_public_id'=>$failure]+$request->validated(),$request,$d,$api); }
    private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api): JsonResponse { $tenant=workcore_tenant(); $idempotency=trim((string)$request->header('Idempotency-Key')); abort_if($idempotency==='',422,'Idempotency-Key header is required.'); $result=$dispatcher->dispatch(new ActionRequest($key,$payload,(int)$tenant->companyId(),(int)$tenant->userId(),$idempotency,$request->header('X-WorkCore-Confirmation'),'api')); return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed)); }
}
