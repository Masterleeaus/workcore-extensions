<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Modules\Supply\Actions\{SearchPurchaseOrders,SearchSuppliers};
use App\Domains\WorkCore\System\Modules\Supply\Http\Requests\{
    CompleteStockCountRequest,
    RateSupplierRequest,
    ReceiveGoodsRequest,
    StoreInventoryCategoryRequest,
    StorePurchaseOrderRequest,
    StoreStockAdjustmentRequest,
    StoreStockCountRequest,
    StoreStockTransferRequest,
    StoreSupplierRequest
};
use Illuminate\Http\{JsonResponse,Request};

final class SupplyController
{
    public function suppliers(Request $request,SearchSuppliers $search,ApiResponseFactory $api):JsonResponse{return $api->paginated($search->execute($request->only(['q','status']),$request->integer('per_page',25)),static fn(array $row):array=>$row);}
    public function purchaseOrders(Request $request,SearchPurchaseOrders $search,ApiResponseFactory $api):JsonResponse{return $api->paginated($search->execute($request->only(['status','supplier_public_id']),$request->integer('per_page',25)),static fn(array $row):array=>$row);}
    public function createCategory(StoreInventoryCategoryRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.inventory.category.create',$request->validated(),$request,$dispatcher,$api);}
    public function createSupplier(StoreSupplierRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.supplier.create',$request->validated(),$request,$dispatcher,$api);}
    public function rateSupplier(string $supplier,RateSupplierRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.supplier.rate',['supplier_public_id'=>$supplier]+$request->validated(),$request,$dispatcher,$api);}
    public function createPurchaseOrder(StorePurchaseOrderRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.purchase_order.create',$request->validated(),$request,$dispatcher,$api);}
    public function approvePurchaseOrder(string $purchaseOrder,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.purchase_order.approve',['purchase_order_public_id'=>$purchaseOrder],$request,$dispatcher,$api);}
    public function receiveGoods(ReceiveGoodsRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.goods_receipt.post',$request->validated(),$request,$dispatcher,$api);}
    public function createTransfer(StoreStockTransferRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.transfer.create',$request->validated(),$request,$dispatcher,$api);}
    public function dispatchTransfer(string $transfer,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.transfer.dispatch',['transfer_public_id'=>$transfer],$request,$dispatcher,$api);}
    public function receiveTransfer(string $transfer,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.transfer.receive',['transfer_public_id'=>$transfer],$request,$dispatcher,$api);}
    public function createStockCount(StoreStockCountRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.stock_count.create',$request->validated(),$request,$dispatcher,$api);}
    public function completeStockCount(string $stockCount,CompleteStockCountRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.stock_count.complete',['stock_count_public_id'=>$stockCount]+$request->validated(),$request,$dispatcher,$api);}
    public function createAdjustment(StoreStockAdjustmentRequest $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.adjustment.create',$request->validated(),$request,$dispatcher,$api);}
    public function postAdjustment(string $adjustment,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse{return $this->dispatch('workcore.supply.adjustment.post',['adjustment_public_id'=>$adjustment],$request,$dispatcher,$api);}

    private function dispatch(string $action,array $payload,Request $request,BusinessActionDispatcher $dispatcher,ApiResponseFactory $api):JsonResponse
    {
        $tenant=workcore_tenant();$idempotency=trim((string)$request->header('Idempotency-Key'));abort_if($idempotency==='',422,'Idempotency-Key header is required.');
        $result=$dispatcher->dispatch(new ActionRequest($action,$payload,$tenant->companyId(),(int)$tenant->userId(),$idempotency,$request->header('X-WorkCore-Confirmation'),'api'));
        return $api->action(new ActionResult($result->actionKey,$result->data,$result->correlationId,$result->auditId,$result->eventIds,$result->replayed));
    }
}
