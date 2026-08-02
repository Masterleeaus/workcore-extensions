<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Wizards\Http\Requests\{ApproveWizardSectionRequest,CreateWizardDefinitionRequest,SaveWizardAnswerRequest,StartWizardRequest};
use App\Domains\WorkCore\System\Modules\Wizards\ReadModels\{GetNextWizardQuestion,GetWizardRun,ListWizardDefinitions};
use Illuminate\Http\{JsonResponse,Request};
final class WizardController
{
    public function index(Request $request,TenantContextContract $tenant,ListWizardDefinitions $list,ApiResponseFactory $api): JsonResponse { return $api->success($list->execute($request->only('category'),(int)$tenant->companyId())); }
    public function show(string $run,TenantContextContract $tenant,GetWizardRun $get,ApiResponseFactory $api): JsonResponse { return $api->success($get->execute($run,(int)$tenant->companyId())); }
    public function next(string $run,TenantContextContract $tenant,GetNextWizardQuestion $get,ApiResponseFactory $api): JsonResponse { return $api->success($get->execute($run,(int)$tenant->companyId())); }
    public function start(StartWizardRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_run.start',$request->validated(),$request,$d,$api); }
    public function answer(string $run,SaveWizardAnswerRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_answer.save',['run_public_id'=>$run]+$request->validated(),$request,$d,$api); }
    public function approve(string $run,ApproveWizardSectionRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_section.approve',['run_public_id'=>$run]+$request->validated(),$request,$d,$api); }
    public function pause(string $run,Request $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_run.pause',['run_public_id'=>$run],$request,$d,$api); }
    public function resume(string $run,Request $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_run.resume',['run_public_id'=>$run],$request,$d,$api); }
    public function complete(string $run,Request $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_run.complete',['run_public_id'=>$run],$request,$d,$api); }
    public function createDefinition(CreateWizardDefinitionRequest $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_definition.create',$request->validated(),$request,$d,$api); }
    public function publishDefinition(string $definition,Request $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.wizard_definition.publish',['definition_public_id'=>$definition],$request,$d,$api); }
    private function dispatch(string $key,array $payload,Request $request,BusinessActionDispatcher $d,ApiResponseFactory $api): JsonResponse { $tenant=app(TenantContextContract::class); $result=$d->dispatch(new ActionRequest($key,$payload,(int)$tenant->companyId(),(int)$tenant->userId(),(string)($request->header('Idempotency-Key')?:$request->input('idempotency_key')?:hash('sha256',$key.json_encode($payload))),$request->header('X-Confirmation-Id')?:$request->input('confirmation_id'),'api')); return $api->success($result->data); }
}
