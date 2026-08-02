<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Http\Requests\{ApplyVerticalSetupRequest,AssignWorkerBusinessLineRequest,IssueWorkOrderCertificateRequest,PreviewVerticalSetupRequest,UpdateBusinessLineRequest,UpsertTradeLicenceRequest};
use App\Domains\WorkCore\System\Http\Requests\{AddWorkOrderEvidenceRequest,CreateWorkOrderCallbackRequest,PrepareWorkOrderComplianceRequest,RecordWorkOrderPermitRequest,RecordWorkOrderSafetyControlRequest,RecordWorkOrderTestResultRequest,RegisterWorkOrderWarrantyRequest,UpsertTradeComplianceProfileRequest};
use App\Domains\WorkCore\System\Verticals\Operations\ReadModels\ListTradeCompliance;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\ReadModels\{GetWorkOrderTradeCompliance,ListTradeComplianceProfiles};
use App\Domains\WorkCore\System\Verticals\ReadModels\{GetCompanyVerticalProfile,ListVerticalCatalogue};
use Illuminate\Http\{JsonResponse,Request};

final class VerticalManagementController
{
    public function catalogue(Request $request, ListVerticalCatalogue $catalogue, ApiResponseFactory $api): JsonResponse
    {
        $primary = $request->string('primary_vertical')->toString() ?: null;
        return $api->success($catalogue->execute(
            $primary,
            $this->list($request->input('add_ons', [])),
            (array) $request->input('answers', []),
        ));
    }

    public function profile(GetCompanyVerticalProfile $profile, ApiResponseFactory $api): JsonResponse
    {
        return $api->success($profile->execute());
    }

    public function preview(
        PreviewVerticalSetupRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.vertical_setup.preview', $request->validated(), $request, $dispatcher, $api);
    }

    public function apply(
        ApplyVerticalSetupRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.vertical_setup.apply', $request->validated(), $request, $dispatcher, $api);
    }

    public function updateBusinessLine(
        string $businessLine,
        UpdateBusinessLineRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch(
            'workcore.business_line.update',
            ['business_line_public_id' => $businessLine] + $request->validated(),
            $request,
            $dispatcher,
            $api,
        );
    }


    public function tradeCompliance(Request $request, ListTradeCompliance $compliance, ApiResponseFactory $api): JsonResponse
    {
        return $api->success($compliance->execute(
            $request->string('vertical_key')->toString() ?: null,
            $request->string('worker_public_id')->toString() ?: null,
        ));
    }

    public function upsertTradeLicence(
        UpsertTradeLicenceRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_licence.upsert', $request->validated(), $request, $dispatcher, $api);
    }

    public function issueWorkOrderCertificate(
        IssueWorkOrderCertificateRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.work_order_certificate.issue', $request->validated(), $request, $dispatcher, $api);
    }

    public function assignWorkerBusinessLine(
        AssignWorkerBusinessLineRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.worker_business_line.assign', $request->validated(), $request, $dispatcher, $api);
    }

    public function tradeComplianceProfiles(Request $request, ListTradeComplianceProfiles $profiles, ApiResponseFactory $api): JsonResponse
    {
        return $api->success($profiles->execute(
            $request->string('vertical_key')->toString() ?: null,
            $request->string('business_line_public_id')->toString() ?: null,
        ));
    }

    public function workOrderTradeCompliance(string $workOrder, GetWorkOrderTradeCompliance $compliance, ApiResponseFactory $api): JsonResponse
    {
        return $api->success($compliance->execute($workOrder));
    }

    public function upsertTradeComplianceProfile(
        UpsertTradeComplianceProfileRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.profile.upsert', $request->validated(), $request, $dispatcher, $api);
    }

    public function prepareTradeCompliance(
        string $workOrder,
        PrepareWorkOrderComplianceRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.prepare', ['work_order_public_id' => $workOrder] + $request->validated(), $request, $dispatcher, $api);
    }

    public function recordTradePermit(
        string $workOrder,
        RecordWorkOrderPermitRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.permit.record', ['work_order_public_id' => $workOrder] + $request->validated(), $request, $dispatcher, $api);
    }

    public function recordTradeSafetyControl(
        string $workOrder,
        RecordWorkOrderSafetyControlRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.safety_control.record', ['work_order_public_id' => $workOrder] + $request->validated(), $request, $dispatcher, $api);
    }

    public function recordTradeTestResult(
        string $workOrder,
        RecordWorkOrderTestResultRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.test_result.record', ['work_order_public_id' => $workOrder] + $request->validated(), $request, $dispatcher, $api);
    }

    public function addTradeEvidence(
        string $workOrder,
        AddWorkOrderEvidenceRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.evidence.add', ['work_order_public_id' => $workOrder] + $request->validated(), $request, $dispatcher, $api);
    }

    public function registerTradeWarranty(
        string $workOrder,
        RegisterWorkOrderWarrantyRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.warranty.register', ['work_order_public_id' => $workOrder] + $request->validated(), $request, $dispatcher, $api);
    }

    public function createTradeCallback(
        CreateWorkOrderCallbackRequest $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        return $this->dispatch('workcore.trade_compliance.callback.create', $request->validated(), $request, $dispatcher, $api);
    }

    /** @param array<string,mixed> $payload */
    private function dispatch(
        string $key,
        array $payload,
        Request $request,
        BusinessActionDispatcher $dispatcher,
        ApiResponseFactory $api,
    ): JsonResponse {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            $key,
            $payload,
            (int) $tenant->companyId(),
            (int) $tenant->userId(),
            $idempotencyKey,
            $request->header('X-WorkCore-Confirmation'),
            'api',
        ));

        return $api->action(new ActionResult(
            $result->actionKey,
            $result->data,
            $result->correlationId,
            $result->auditId,
            $result->eventIds,
            $result->replayed,
        ), 200);
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            is_array($value) ? $value : [],
        ), static fn (string $item): bool => $item !== ''));
    }
}
