<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Controllers;
use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\InventoryItemResource;
use App\Domains\WorkCore\System\Modules\Inventory\Actions\{ListStockLocations,SearchInventory};
use App\Domains\WorkCore\System\Modules\Inventory\Http\Requests\{ConsumeStockReservationRequest,RecordStockMovementRequest,ReleaseStockReservationRequest,ReserveInventoryItemRequest,SetReorderRuleRequest,StoreInventoryItemRequest,StoreStockLocationRequest};
use Illuminate\Http\{JsonResponse,Request};
final class InventoryController
{
    public function index(Request $request, SearchInventory $search, ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated($search->execute($request->only(['q','item_type','active','low_stock']), $request->integer('per_page', 25)), InventoryItemResource::make(...));
    }
    public function locations(Request $request, ListStockLocations $locations, ApiResponseFactory $api): JsonResponse
    {
        return $api->success(array_map(static fn (array $row): array => ['id' => $row['public_id'] ?? null, 'type' => 'stock_location', 'attributes' => array_diff_key($row, ['public_id'=>true,'company_id'=>true,'id'=>true])], $locations->execute($request->only(['location_type','active']))));
    }
    public function store(StoreInventoryItemRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.inventory_item.create', $request->validated(), $request, $dispatcher, $api); }
    public function storeLocation(StoreStockLocationRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.stock_location.create', $request->validated(), $request, $dispatcher, $api); }
    public function movement(string $item, RecordStockMovementRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.stock.record_movement', ['item_public_id'=>$item] + $request->validated(), $request, $dispatcher, $api); }
    public function reserve(string $item, ReserveInventoryItemRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.stock.reserve', ['item_public_id'=>$item] + $request->validated(), $request, $dispatcher, $api); }
    public function consume(string $reservation, ConsumeStockReservationRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.stock.consume_reservation', ['reservation_public_id'=>$reservation] + $request->validated(), $request, $dispatcher, $api); }
    public function release(string $reservation, ReleaseStockReservationRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.stock.release_reservation', ['reservation_public_id'=>$reservation] + $request->validated(), $request, $dispatcher, $api); }
    public function reorderRule(string $item, SetReorderRuleRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse { return $this->dispatch('workcore.inventory.set_reorder_rule', ['item_public_id'=>$item] + $request->validated(), $request, $dispatcher, $api); }
    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotency = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotency === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest($key, $payload, (int) $tenant->companyId(), (int) $tenant->userId(), $idempotency, $request->header('X-WorkCore-Confirmation'), 'api'));
        return $api->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed));
    }
}
