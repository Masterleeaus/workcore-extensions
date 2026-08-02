<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest, ActionResult, BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Api\Resources\AssetResource;
use App\Domains\WorkCore\System\Modules\Assets\Actions\{GetAssetAvailability, LookupAssetIdentifier, SearchAssets, SearchOverdueAssetCustody};
use App\Domains\WorkCore\System\Modules\Assets\Http\Requests\{
    AssignAssetRequest,
    ChangeAssetStatusRequest,
    CheckinAssetRequest,
    CheckoutAssetRequest,
    CompleteAssetMaintenanceRequest,
    CreateAssetSupplyHandoffRequest,
    LinkAssetDocumentRequest,
    RecordAssetCalibrationRequest,
    RecordAssetConditionRequest,
    OverrideAssetStatusRequest,
    RegisterAssetIdentifierRequest,
    ReportAssetDamageRequest,
    StartAssetMaintenanceRequest,
    StoreAssetCategoryRequest,
    StoreAssetMaintenancePlanRequest,
    StoreAssetMaintenanceRequest,
    StoreAssetMeterReadingRequest,
    StoreAssetRequest,
    StoreAssetWarrantyClaimRequest,
    StoreAssetWarrantyRequest,
    TransferAssetRequest,
    UpdateAssetWarrantyClaimRequest,
    WriteOffAssetRequest
};
use Illuminate\Http\{JsonResponse, Request};

final class AssetController
{
    public function __construct(private PermissionResolverContract $permissions) {}
    public function index(Request $request, SearchAssets $search, ApiResponseFactory $api): JsonResponse
    {
        $this->assertCanView();
        $filters = $request->only([
            'q', 'status', 'asset_type', 'criticality', 'condition', 'premises_public_id',
            'category_public_id', 'worker_public_id', 'due_before', 'serial_number', 'supply_item_public_id',
        ]);

        return $api->paginated(
            $search->execute($filters, $request->integer('per_page', 25)),
            AssetResource::make(...),
        );
    }

    public function availability(string $asset, GetAssetAvailability $read, ApiResponseFactory $api): JsonResponse
    {
        $this->assertCanView();
        return $api->success($read->execute($asset));
    }

    public function lookupIdentifier(Request $request, LookupAssetIdentifier $read, ApiResponseFactory $api): JsonResponse
    {
        $this->assertCanView();
        $value = trim((string) $request->query('value'));
        abort_if($value === '', 422, 'Identifier value is required.');
        $record = $read->execute($value, $request->query('type'));
        abort_if($record === null, 404, 'Asset identifier not found.');

        return $api->success($record);
    }

    public function overdueCustody(Request $request, SearchOverdueAssetCustody $read, ApiResponseFactory $api): JsonResponse
    {
        $this->assertCanView();
        return $api->success($read->execute($request->only(['before', 'assignment_type', 'worker_public_id'])));
    }

    public function storeCategory(StoreAssetCategoryRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset_category.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function store(StoreAssetRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function supplyHandoff(CreateAssetSupplyHandoffRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $payload = $request->validated();
        $payload['idempotency_key'] ??= trim((string) $request->header('Idempotency-Key'));

        return $this->dispatch('workcore.asset.supply_handoff.create', $payload, $request, $dispatcher, $api);
    }

    public function assign(string $asset, AssignAssetRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.assign', $asset, $request, $dispatcher, $api);
    }

    public function checkout(string $asset, CheckoutAssetRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.checkout', $asset, $request, $dispatcher, $api);
    }

    public function checkin(string $asset, CheckinAssetRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.checkin', $asset, $request, $dispatcher, $api);
    }

    public function transfer(string $asset, TransferAssetRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.transfer', $asset, $request, $dispatcher, $api);
    }

    public function status(string $asset, ChangeAssetStatusRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.change_status', $asset, $request, $dispatcher, $api);
    }

    public function writeoff(string $asset, WriteOffAssetRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.writeoff', ['asset_public_id' => $asset, 'status' => 'written_off'] + $request->validated(), $request, $dispatcher, $api);
    }

    public function overrideStatus(string $asset, OverrideAssetStatusRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.override_status', ['asset_public_id' => $asset, 'override' => true] + $request->validated(), $request, $dispatcher, $api);
    }

    public function condition(string $asset, RecordAssetConditionRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.condition.record', $asset, $request, $dispatcher, $api);
    }

    public function damage(string $asset, ReportAssetDamageRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.damage.report', $asset, $request, $dispatcher, $api);
    }

    public function maintenancePlan(string $asset, StoreAssetMaintenancePlanRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.maintenance_plan.create', $asset, $request, $dispatcher, $api);
    }

    public function maintenance(string $asset, StoreAssetMaintenanceRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.record_maintenance', $asset, $request, $dispatcher, $api);
    }

    public function startMaintenance(string $asset, string $maintenance, StartAssetMaintenanceRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.maintenance.start', ['asset_public_id' => $asset, 'maintenance_public_id' => $maintenance] + $request->validated(), $request, $dispatcher, $api);
    }

    public function completeMaintenance(string $asset, string $maintenance, CompleteAssetMaintenanceRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.maintenance.complete', ['asset_public_id' => $asset, 'maintenance_public_id' => $maintenance] + $request->validated(), $request, $dispatcher, $api);
    }

    public function meterReading(string $asset, StoreAssetMeterReadingRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.record_meter_reading', $asset, $request, $dispatcher, $api);
    }

    public function calibration(string $asset, RecordAssetCalibrationRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.calibration.record', $asset, $request, $dispatcher, $api);
    }

    public function warranty(string $asset, StoreAssetWarrantyRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.warranty.create', $asset, $request, $dispatcher, $api);
    }

    public function warrantyClaim(string $asset, string $warranty, StoreAssetWarrantyClaimRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.warranty_claim.create', ['asset_public_id' => $asset, 'warranty_public_id' => $warranty] + $request->validated(), $request, $dispatcher, $api);
    }

    public function updateWarrantyClaim(string $asset, string $claim, UpdateAssetWarrantyClaimRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.asset.warranty_claim.update', ['asset_public_id' => $asset, 'claim_public_id' => $claim] + $request->validated(), $request, $dispatcher, $api);
    }

    public function identifier(string $asset, RegisterAssetIdentifierRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.identifier.register', $asset, $request, $dispatcher, $api);
    }

    public function document(string $asset, LinkAssetDocumentRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->assetDispatch('workcore.asset.document.link', $asset, $request, $dispatcher, $api);
    }

    private function assertCanView(): void
    {
        $tenant = workcore_tenant();
        abort_unless(
            $this->permissions->allows((int) $tenant->userId(), (int) $tenant->companyId(), (string) config('workcore.assets.permissions.view')),
            403,
            'The actor is not permitted to view assets.',
        );
    }

    private function assetDispatch(string $key, string $asset, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        /** @var array<string,mixed> $validated */
        $validated = method_exists($request, 'validated') ? $request->validated() : [];

        return $this->dispatch($key, ['asset_public_id' => $asset] + $validated, $request, $dispatcher, $api);
    }

    /** @param array<string,mixed> $payload */
    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotency === '', 422, 'Idempotency-Key header is required.');

        $result = $dispatcher->dispatch(new ActionRequest(
            $key,
            $payload,
            (int) $tenant->companyId(),
            (int) $tenant->userId(),
            $idempotency,
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
        ));
    }
}
