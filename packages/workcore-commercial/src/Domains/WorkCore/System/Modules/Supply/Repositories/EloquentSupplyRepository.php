<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentSupplyRepository extends TenantScopedRepository implements SupplyRepositoryContract
{
    public function __construct(ConnectionInterface $db, TenantContextContract $tenant) { parent::__construct($db, $tenant); }

    public function searchSuppliers(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        return $this->db->table('tz_suppliers')->where('company_id',$companyId)->whereNull('deleted_at')
            ->when($filters['status']??null,fn($q,$v)=>$q->where('status',$v))
            ->when($filters['q']??null,fn($q,$v)=>$q->where(fn($n)=>$n->where('name','like',"%{$v}%")->orWhere('supplier_number','like',"%{$v}%")))
            ->orderBy('name')->paginate(max(1,min($perPage,100)));
    }

    public function searchPurchaseOrders(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        return $this->db->table('tz_purchase_orders as po')->join('tz_suppliers as s',fn($j)=>$j->on('s.id','=','po.supplier_id')->on('s.company_id','=','po.company_id'))
            ->where('po.company_id',$companyId)->whereNull('po.deleted_at')->whereNull('s.deleted_at')
            ->when($filters['status']??null,fn($q,$v)=>$q->where('po.status',$v))
            ->when($filters['supplier_public_id']??null,fn($q,$v)=>$q->where('s.public_id',$v))
            ->select(['po.*','s.public_id as supplier_public_id','s.name as supplier_name'])->orderByDesc('po.id')->paginate(max(1,min($perPage,100)));
    }

    public function createCategory(array $data, int $companyId): array
    {
        $this->assertCompany($companyId);
        $parentId=null;
        if(!empty($data['parent_public_id'])){$parent=$this->record('tz_inventory_categories',(string)$data['parent_public_id'],$companyId);$parentId=(int)$parent->id;}
        $publicId=(string)Str::ulid();
        $this->db->table('tz_inventory_categories')->insert(['public_id'=>$publicId,'company_id'=>$companyId,'parent_id'=>$parentId,'code'=>$data['code'],'name'=>$data['name'],'description'=>$data['description']??null,'active'=>$data['active']??true,'created_at'=>now(),'updated_at'=>now()]);
        return $this->byPublicId('tz_inventory_categories',$companyId,$publicId);
    }

    public function createSupplier(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$publicId=(string)Str::ulid();
        $this->db->table('tz_suppliers')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'supplier_number'=>$data['supplier_number'],'name'=>$data['name'],'status'=>$data['status']??'active',
            'contact_name'=>$data['contact_name']??null,'email'=>$data['email']??null,'phone'=>$data['phone']??null,'website'=>$data['website']??null,
            'tax_identifier'=>$data['tax_identifier']??null,'payment_terms'=>$data['payment_terms']??null,'currency'=>$data['currency']??'AUD','address'=>$data['address']??null,
            'capabilities'=>isset($data['capabilities'])?json_encode($data['capabilities']):null,'metadata'=>isset($data['metadata'])?json_encode($data['metadata']):null,
            'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_suppliers',$companyId,$publicId);
    }

    public function rateSupplier(string $supplierPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$supplier=$this->record('tz_suppliers',$supplierPublicId,$companyId);
        $publicId=(string)Str::ulid();
        $this->db->table('tz_supplier_ratings')->insert([
            'public_id'=>$publicId,'company_id'=>$companyId,'supplier_id'=>$supplier->id,'quality_rating'=>$data['quality_rating'],'delivery_rating'=>$data['delivery_rating'],
            'service_rating'=>$data['service_rating'],'notes'=>$data['notes']??null,'rated_by_user_id'=>$actorId,'rated_at'=>$data['rated_at']??now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return $this->byPublicId('tz_supplier_ratings',$companyId,$publicId);
    }

    public function createPurchaseOrder(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$supplier=$this->record('tz_suppliers',(string)$data['supplier_public_id'],$companyId);
        $locationId=null;if(!empty($data['delivery_location_public_id'])){$locationId=(int)$this->record('tz_stock_locations',(string)$data['delivery_location_public_id'],$companyId)->id;}
        $items=(array)($data['items']??[]);if($items===[]){throw new InvalidArgumentException('Purchase order requires at least one item.');}
        return $this->db->transaction(function()use($data,$items,$companyId,$actorId,$supplier,$locationId):array{
            $subtotal=0.0;$tax=0.0;$resolved=[];
            foreach($items as $line){
                $itemId=null;if(!empty($line['item_public_id'])){$itemId=(int)$this->record('tz_inventory_items',(string)$line['item_public_id'],$companyId)->id;}
                $qty=(float)$line['quantity'];$unit=(float)$line['unit_cost'];$rate=(float)($line['tax_rate']??0);if($qty<=0||$unit<0){throw new InvalidArgumentException('Purchase quantities and costs are invalid.');}
                $lineNet=$qty*$unit;$lineTax=$lineNet*$rate/100;$subtotal+=$lineNet;$tax+=$lineTax;
                $resolved[]=['item_id'=>$itemId,'description'=>$line['description'],'ordered_quantity'=>$qty,'unit_cost'=>$unit,'tax_rate'=>$rate,'line_total'=>$lineNet+$lineTax,'metadata'=>$line['metadata']??null];
            }
            $publicId=(string)Str::ulid();$id=$this->db->table('tz_purchase_orders')->insertGetId([
                'public_id'=>$publicId,'company_id'=>$companyId,'supplier_id'=>$supplier->id,'delivery_location_id'=>$locationId,'order_number'=>$data['order_number'],
                'status'=>'draft','currency'=>$data['currency']??'AUD','ordered_on'=>$data['ordered_on']??null,'expected_on'=>$data['expected_on']??null,
                'subtotal'=>$subtotal,'tax_total'=>$tax,'total'=>$subtotal+$tax,'supplier_reference'=>$data['supplier_reference']??null,'notes'=>$data['notes']??null,
                'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($resolved as $line){$this->db->table('tz_purchase_order_items')->insert([
                'public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'purchase_order_id'=>$id,'item_id'=>$line['item_id'],'description'=>$line['description'],
                'ordered_quantity'=>$line['ordered_quantity'],'received_quantity'=>0,'unit_cost'=>$line['unit_cost'],'tax_rate'=>$line['tax_rate'],'line_total'=>$line['line_total'],
                'metadata'=>$line['metadata']?json_encode($line['metadata']):null,'created_at'=>now(),'updated_at'=>now(),
            ]);}
            return $this->purchaseOrder($companyId,$publicId);
        },3);
    }

    public function approvePurchaseOrder(string $purchaseOrderPublicId, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$po=$this->record('tz_purchase_orders',$purchaseOrderPublicId,$companyId);
        if(!in_array($po->status,['draft','submitted'],true)){throw new InvalidArgumentException('Only draft or submitted purchase orders may be approved.');}
        $this->db->table('tz_purchase_orders')->where('id',$po->id)->update(['status'=>'approved','approved_by_user_id'=>$actorId,'approved_at'=>now(),'ordered_on'=>$po->ordered_on??now()->toDateString(),'updated_at'=>now()]);
        return $this->purchaseOrder($companyId,$purchaseOrderPublicId);
    }

    public function receiveGoods(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $location = $this->record('tz_stock_locations', (string) $data['location_public_id'], $companyId);
        $po = null;
        $supplier = null;
        if (! empty($data['purchase_order_public_id'])) {
            $po = $this->record('tz_purchase_orders', (string) $data['purchase_order_public_id'], $companyId);
            if (! in_array($po->status, ['approved', 'partially_received'], true)) {
                throw new InvalidArgumentException('Purchase order is not receivable.');
            }
            $supplier = $this->db->table('tz_suppliers')
                ->where('company_id', $companyId)
                ->where('id', $po->supplier_id)
                ->whereNull('deleted_at')
                ->first();
        } elseif (! empty($data['supplier_public_id'])) {
            $supplier = $this->record('tz_suppliers', (string) $data['supplier_public_id'], $companyId);
        }
        $lines = (array) ($data['items'] ?? []);
        if ($lines === []) {
            throw new InvalidArgumentException('Goods receipt requires items.');
        }

        return $this->db->transaction(function () use ($data, $lines, $companyId, $actorId, $location, $po, $supplier): array {
            $publicId = (string) Str::ulid();
            $receiptId = $this->db->table('tz_goods_receipts')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'purchase_order_id' => $po?->id,
                'supplier_id' => $supplier?->id,
                'location_id' => $location->id,
                'receipt_number' => $data['receipt_number'],
                'status' => 'posted',
                'received_at' => $data['received_at'] ?? now(),
                'delivery_reference' => $data['delivery_reference'] ?? null,
                'evidence_file_references' => isset($data['evidence_file_references']) ? json_encode($data['evidence_file_references']) : null,
                'notes' => $data['notes'] ?? null,
                'received_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                $item = $this->record('tz_inventory_items', (string) $line['item_public_id'], $companyId);
                $accepted = (float) $line['accepted_quantity'];
                $rejected = (float) ($line['rejected_quantity'] ?? 0);
                $received = $accepted + $rejected;
                if ($accepted < 0 || $rejected < 0 || $received <= 0) {
                    throw new InvalidArgumentException('Receipt quantities are invalid.');
                }
                if (($line['create_asset'] ?? false) === true) {
                    if ($accepted !== 1.0 || empty($line['serial_number'])) {
                        throw new InvalidArgumentException('Asset handoff requires exactly one accepted serialized unit.');
                    }
                }
                $poItemId = null;
                if (! empty($line['purchase_order_item_public_id'])) {
                    if (! $po) {
                        throw new InvalidArgumentException('Purchase order line requires a selected purchase order.');
                    }
                    $poItem = $this->record('tz_purchase_order_items', (string) $line['purchase_order_item_public_id'], $companyId);
                    if ((int) $poItem->purchase_order_id !== (int) $po->id) {
                        throw new InvalidArgumentException('Purchase order line does not belong to selected order.');
                    }
                    if ($poItem->item_id !== null && (int) $poItem->item_id !== (int) $item->id) {
                        throw new InvalidArgumentException('Received item does not match the purchase order line.');
                    }
                    if ((float) $poItem->received_quantity + $accepted > (float) $poItem->ordered_quantity) {
                        throw new InvalidArgumentException('Receipt exceeds the remaining purchase order quantity.');
                    }
                    $poItemId = (int) $poItem->id;
                }
                $batchId = null;
                if ($accepted > 0 && ! empty($line['batch_number'])) {
                    $batchId = $this->upsertBatch($companyId, (int) $item->id, (int) $location->id, (string) $line['batch_number'], $line, $accepted);
                }
                $receiptItemPublic = (string) Str::ulid();
                $this->db->table('tz_goods_receipt_items')->insert([
                    'public_id' => $receiptItemPublic,
                    'company_id' => $companyId,
                    'goods_receipt_id' => $receiptId,
                    'purchase_order_item_id' => $poItemId,
                    'item_id' => $item->id,
                    'batch_id' => $batchId,
                    'received_quantity' => $received,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'serial_number' => $line['serial_number'] ?? null,
                    'expires_on' => $line['expires_on'] ?? null,
                    'condition' => $line['condition'] ?? ($rejected > 0 ? 'partially_rejected' : 'accepted'),
                    'rejection_reason' => $line['rejection_reason'] ?? null,
                    'metadata' => isset($line['metadata']) ? json_encode($line['metadata']) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($accepted > 0) {
                    $this->changeBalance($companyId, (int) $item->id, (int) $location->id, $accepted);
                    $this->insertMovement($companyId, (int) $item->id, null, (int) $location->id, 'receipt', $accepted, $data['receipt_number'], $actorId, $line['unit_cost'] ?? null);
                }
                if ($poItemId) {
                    $this->db->table('tz_purchase_order_items')->where('id', $poItemId)->increment('received_quantity', $accepted, ['updated_at' => now()]);
                }
                if ($accepted === 1.0 && ($line['create_asset'] ?? false) === true) {
                    $this->createAssetHandoff($companyId, $item, $receiptItemPublic, $po, $supplier, (string) $line['serial_number'], $actorId, $line);
                }
            }
            if ($po) {
                $remaining = $this->db->table('tz_purchase_order_items')
                    ->where('company_id', $companyId)
                    ->where('purchase_order_id', $po->id)
                    ->whereRaw('received_quantity < ordered_quantity')
                    ->exists();
                $this->db->table('tz_purchase_orders')->where('id', $po->id)->update([
                    'status' => $remaining ? 'partially_received' : 'received',
                    'updated_at' => now(),
                ]);
            }

            return $this->goodsReceipt($companyId, $publicId);
        }, 3);
    }

    public function createTransfer(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $from = $this->record('tz_stock_locations', (string) $data['from_location_public_id'], $companyId);
        $to = $this->record('tz_stock_locations', (string) $data['to_location_public_id'], $companyId);
        if ((int) $from->id === (int) $to->id) {
            throw new InvalidArgumentException('Transfer locations must differ.');
        }
        $lines = (array) ($data['items'] ?? []);
        if ($lines === []) {
            throw new InvalidArgumentException('Transfer requires items.');
        }

        return $this->db->transaction(function () use ($data, $lines, $companyId, $actorId, $from, $to): array {
            $publicId = (string) Str::ulid();
            $id = $this->db->table('tz_stock_transfers')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'transfer_number' => $data['transfer_number'],
                'status' => 'requested',
                'requested_at' => now(),
                'requested_by_user_id' => $actorId,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                $item = $this->record('tz_inventory_items', (string) $line['item_public_id'], $companyId);
                $qty = (float) $line['quantity'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Transfer quantity must be positive.');
                }
                $batchId = null;
                if (! empty($line['batch_public_id'])) {
                    $batch = $this->assertBatchMatches(
                        (string) $line['batch_public_id'],
                        (int) $item->id,
                        (int) $from->id,
                        $companyId,
                    );
                    $available = (float) $batch->quantity_on_hand - (float) $batch->quantity_reserved;
                    if ($qty > $available) {
                        throw new InvalidArgumentException('Transfer exceeds available batch quantity.');
                    }
                    $batchId = (int) $batch->id;
                }
                $this->db->table('tz_stock_transfer_items')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'transfer_id' => $id,
                    'item_id' => $item->id,
                    'batch_id' => $batchId,
                    'requested_quantity' => $qty,
                    'dispatched_quantity' => 0,
                    'received_quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->transfer($companyId, $publicId);
        }, 3);
    }

    public function dispatchTransfer(string $transferPublicId, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($transferPublicId, $companyId, $actorId): array {
            $transfer = $this->locked('tz_stock_transfers', $transferPublicId, $companyId);
            if ($transfer->status !== 'requested') {
                throw new InvalidArgumentException('Only requested transfers may be dispatched.');
            }
            $lines = $this->db->table('tz_stock_transfer_items')
                ->where('company_id', $companyId)
                ->where('transfer_id', $transfer->id)
                ->lockForUpdate()
                ->get();
            foreach ($lines as $line) {
                $qty = (float) $line->requested_quantity;
                $this->changeBalance($companyId, (int) $line->item_id, (int) $transfer->from_location_id, -$qty);
                if ($line->batch_id !== null) {
                    $this->changeBatchQuantity($companyId, (int) $line->batch_id, -$qty);
                }
                $this->insertMovement($companyId, (int) $line->item_id, (int) $transfer->from_location_id, null, 'transfer_dispatch', $qty, $transfer->transfer_number, $actorId, null);
                $this->db->table('tz_stock_transfer_items')->where('id', $line->id)->update([
                    'dispatched_quantity' => $qty,
                    'updated_at' => now(),
                ]);
            }
            $this->db->table('tz_stock_transfers')->where('id', $transfer->id)->update([
                'status' => 'in_transit',
                'dispatched_at' => now(),
                'approved_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);

            return $this->transfer($companyId, $transferPublicId);
        }, 3);
    }

    public function receiveTransfer(string $transferPublicId, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($transferPublicId, $companyId, $actorId): array {
            $transfer = $this->locked('tz_stock_transfers', $transferPublicId, $companyId);
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException('Only dispatched transfers may be received.');
            }
            $lines = $this->db->table('tz_stock_transfer_items')
                ->where('company_id', $companyId)
                ->where('transfer_id', $transfer->id)
                ->lockForUpdate()
                ->get();
            foreach ($lines as $line) {
                $qty = (float) $line->dispatched_quantity;
                $this->changeBalance($companyId, (int) $line->item_id, (int) $transfer->to_location_id, $qty);
                if ($line->batch_id !== null) {
                    $this->receiveTransferredBatch(
                        $companyId,
                        (int) $line->batch_id,
                        (int) $line->item_id,
                        (int) $transfer->to_location_id,
                        $qty,
                    );
                }
                $this->insertMovement($companyId, (int) $line->item_id, null, (int) $transfer->to_location_id, 'transfer_receive', $qty, $transfer->transfer_number, $actorId, null);
                $this->db->table('tz_stock_transfer_items')->where('id', $line->id)->update([
                    'received_quantity' => $qty,
                    'updated_at' => now(),
                ]);
            }
            $this->db->table('tz_stock_transfers')->where('id', $transfer->id)->update([
                'status' => 'received',
                'received_at' => now(),
                'received_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);

            return $this->transfer($companyId, $transferPublicId);
        }, 3);
    }

    public function createStockCount(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $location = $this->record('tz_stock_locations', (string) $data['location_public_id'], $companyId);

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $location): array {
            $publicId = (string) Str::ulid();
            $id = $this->db->table('tz_stock_counts')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'location_id' => $location->id,
                'count_number' => $data['count_number'],
                'count_type' => $data['count_type'] ?? 'cycle',
                'status' => 'open',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'started_at' => now(),
                'counted_by_user_id' => $actorId,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $batchedItems = [];
            $batches = $this->db->table('tz_stock_batches')
                ->where('company_id', $companyId)
                ->where('location_id', $location->id)
                ->where('status', '!=', 'quarantined')
                ->get();
            foreach ($batches as $batch) {
                $batchedItems[(int) $batch->item_id] = true;
                $this->db->table('tz_stock_count_items')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'stock_count_id' => $id,
                    'item_id' => $batch->item_id,
                    'batch_id' => $batch->id,
                    'expected_quantity' => $batch->quantity_on_hand,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $balances = $this->db->table('tz_stock_balances')
                ->where('company_id', $companyId)
                ->where('location_id', $location->id)
                ->get();
            foreach ($balances as $balance) {
                if (isset($batchedItems[(int) $balance->item_id])) {
                    continue;
                }
                $this->db->table('tz_stock_count_items')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'stock_count_id' => $id,
                    'item_id' => $balance->item_id,
                    'batch_id' => null,
                    'expected_quantity' => $balance->quantity_on_hand,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->stockCount($companyId, $publicId);
        }, 3);
    }

    public function completeStockCount(string $stockCountPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $count = $this->record('tz_stock_counts', $stockCountPublicId, $companyId);
        if (! in_array($count->status, ['open', 'counting'], true)) {
            throw new InvalidArgumentException('Stock count is not open.');
        }

        return $this->db->transaction(function () use ($count, $data, $companyId, $actorId): array {
            foreach ((array) ($data['items'] ?? []) as $line) {
                $item = $this->record('tz_inventory_items', (string) $line['item_public_id'], $companyId);
                $batchId = null;
                $expected = null;
                if (! empty($line['batch_public_id'])) {
                    $batch = $this->assertBatchMatches((string) $line['batch_public_id'], (int) $item->id, (int) $count->location_id, $companyId);
                    $batchId = (int) $batch->id;
                    $expected = (float) $batch->quantity_on_hand;
                }
                $query = $this->db->table('tz_stock_count_items')
                    ->where('company_id', $companyId)
                    ->where('stock_count_id', $count->id)
                    ->where('item_id', $item->id);
                $batchId === null ? $query->whereNull('batch_id') : $query->where('batch_id', $batchId);
                $row = $query->first();
                if (! $row) {
                    if ($expected === null) {
                        $expected = (float) ($this->db->table('tz_stock_balances')
                            ->where('company_id', $companyId)
                            ->where('location_id', $count->location_id)
                            ->where('item_id', $item->id)
                            ->value('quantity_on_hand') ?? 0);
                    }
                    $id = $this->db->table('tz_stock_count_items')->insertGetId([
                        'public_id' => (string) Str::ulid(),
                        'company_id' => $companyId,
                        'stock_count_id' => $count->id,
                        'item_id' => $item->id,
                        'batch_id' => $batchId,
                        'expected_quantity' => $expected,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $row = $this->db->table('tz_stock_count_items')->where('id', $id)->first();
                }
                $counted = (float) $line['counted_quantity'];
                $variance = $counted - (float) $row->expected_quantity;
                $this->db->table('tz_stock_count_items')->where('id', $row->id)->update([
                    'counted_quantity' => $counted,
                    'variance_quantity' => $variance,
                    'variance_reason' => $line['variance_reason'] ?? null,
                    'updated_at' => now(),
                ]);
            }
            $this->db->table('tz_stock_counts')->where('id', $count->id)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'approved_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);

            return $this->stockCount($companyId, $count->public_id);
        }, 3);
    }

    public function createAdjustment(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $location = $this->record('tz_stock_locations', (string) $data['location_public_id'], $companyId);
        $countId = null;
        if (! empty($data['stock_count_public_id'])) {
            $count = $this->record('tz_stock_counts', (string) $data['stock_count_public_id'], $companyId);
            if ((int) $count->location_id !== (int) $location->id) {
                throw new InvalidArgumentException('Stock count does not belong to the selected location.');
            }
            $countId = (int) $count->id;
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $location, $countId): array {
            $publicId = (string) Str::ulid();
            $id = $this->db->table('tz_stock_adjustments')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'location_id' => $location->id,
                'stock_count_id' => $countId,
                'adjustment_number' => $data['adjustment_number'],
                'reason_code' => $data['reason_code'],
                'status' => 'pending_approval',
                'reason' => $data['reason'] ?? null,
                'requested_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ((array) $data['items'] as $line) {
                $item = $this->record('tz_inventory_items', (string) $line['item_public_id'], $companyId);
                $delta = (float) $line['quantity_delta'];
                if (abs($delta) < 0.00001) {
                    throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
                }
                $batchId = null;
                if (! empty($line['batch_public_id'])) {
                    $batch = $this->assertBatchMatches((string) $line['batch_public_id'], (int) $item->id, (int) $location->id, $companyId);
                    $batchId = (int) $batch->id;
                    if ((float) $batch->quantity_on_hand + $delta < 0) {
                        throw new InvalidArgumentException('Batch adjustment would create negative stock.');
                    }
                }
                $this->db->table('tz_stock_adjustment_items')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'adjustment_id' => $id,
                    'item_id' => $item->id,
                    'batch_id' => $batchId,
                    'quantity_delta' => $delta,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'notes' => $line['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->adjustment($companyId, $publicId);
        }, 3);
    }

    public function postAdjustment(string $adjustmentPublicId, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($adjustmentPublicId, $companyId, $actorId): array {
            $adjustment = $this->locked('tz_stock_adjustments', $adjustmentPublicId, $companyId);
            if ($adjustment->status !== 'pending_approval') {
                throw new InvalidArgumentException('Adjustment is not pending approval.');
            }
            $lines = $this->db->table('tz_stock_adjustment_items')
                ->where('company_id', $companyId)
                ->where('adjustment_id', $adjustment->id)
                ->lockForUpdate()
                ->get();
            foreach ($lines as $line) {
                $delta = (float) $line->quantity_delta;
                $this->changeBalance($companyId, (int) $line->item_id, (int) $adjustment->location_id, $delta);
                if ($line->batch_id !== null) {
                    $this->changeBatchQuantity($companyId, (int) $line->batch_id, $delta);
                }
                $this->insertMovement(
                    $companyId,
                    (int) $line->item_id,
                    $delta < 0 ? (int) $adjustment->location_id : null,
                    $delta > 0 ? (int) $adjustment->location_id : null,
                    'adjustment',
                    abs($delta),
                    $adjustment->adjustment_number,
                    $actorId,
                    $line->unit_cost,
                );
            }
            $this->db->table('tz_stock_adjustments')->where('id', $adjustment->id)->update([
                'status' => 'posted',
                'approved_by_user_id' => $actorId,
                'approved_at' => now(),
                'posted_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->adjustment($companyId, $adjustmentPublicId);
        }, 3);
    }

    private function assertBatchMatches(string $batchPublicId, int $itemId, int $locationId, int $companyId): object
    {
        $this->assertCompany($companyId);
        $batch = $this->db->table('tz_stock_batches')
            ->where('company_id', $companyId)
            ->where('public_id', $batchPublicId)
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();
        if (! $batch) {
            throw new InvalidArgumentException('Batch does not belong to the selected item and location.');
        }

        return $batch;
    }

    private function changeBatchQuantity(int $companyId, int $batchId, float $delta): void
    {
        $batch = $this->db->table('tz_stock_batches')
            ->where('company_id', $companyId)
            ->where('id', $batchId)
            ->lockForUpdate()
            ->first();
        if (! $batch) {
            throw new InvalidArgumentException('Batch is unavailable in the active company.');
        }
        $newQuantity = (float) $batch->quantity_on_hand + $delta;
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('Operation would create negative batch stock.');
        }
        $this->db->table('tz_stock_batches')->where('id', $batchId)->update([
            'quantity_on_hand' => $newQuantity,
            'status' => $newQuantity <= 0.00001 ? 'depleted' : 'available',
            'updated_at' => now(),
        ]);
    }

    private function receiveTransferredBatch(int $companyId, int $sourceBatchId, int $itemId, int $destinationLocationId, float $quantity): int
    {
        $source = $this->db->table('tz_stock_batches')
            ->where('company_id', $companyId)
            ->where('id', $sourceBatchId)
            ->where('item_id', $itemId)
            ->first();
        if (! $source) {
            throw new InvalidArgumentException('Source batch is unavailable in the active company.');
        }
        $destination = $this->db->table('tz_stock_batches')
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('location_id', $destinationLocationId)
            ->where('batch_number', $source->batch_number)
            ->lockForUpdate()
            ->first();
        if ($destination) {
            $this->changeBatchQuantity($companyId, (int) $destination->id, $quantity);

            return (int) $destination->id;
        }

        return $this->db->table('tz_stock_batches')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'item_id' => $itemId,
            'location_id' => $destinationLocationId,
            'batch_number' => $source->batch_number,
            'serial_number' => $source->serial_number,
            'manufactured_on' => $source->manufactured_on,
            'expires_on' => $source->expires_on,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
            'unit_cost' => $source->unit_cost,
            'status' => 'available',
            'metadata' => $source->metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAssetHandoff(int $companyId, object $item, string $receiptItemPublicId, ?object $po, ?object $supplier, string $serial, int $actorId, array $line): void
    {
        $supplyId=(string)($item->supply_item_public_id?:$item->public_id);$key='receipt:'.$receiptItemPublicId.':'.$serial;
        $this->db->table('tz_asset_supply_handoffs')->insertOrIgnore(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'idempotency_key'=>$key,'inventory_item_id'=>$item->id,'supply_item_public_id'=>$supplyId,'supply_goods_receipt_item_public_id'=>$receiptItemPublicId,'supply_purchase_order_public_id'=>$po?->public_id,'supplier_public_id'=>$supplier?->public_id,'serial_number'=>$serial,'source_payload_hash'=>hash('sha256',json_encode($line,JSON_THROW_ON_ERROR)),'source_payload'=>json_encode($line),'status'=>'pending','processed_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function upsertBatch(int $companyId,int $itemId,int $locationId,string $batch,array $line,float $accepted): int
    {
        $row=$this->db->table('tz_stock_batches')->where('company_id',$companyId)->where('item_id',$itemId)->where('location_id',$locationId)->where('batch_number',$batch)->lockForUpdate()->first();
        if($row){$this->db->table('tz_stock_batches')->where('id',$row->id)->update(['quantity_on_hand'=>(float)$row->quantity_on_hand+$accepted,'expires_on'=>$line['expires_on']??$row->expires_on,'updated_at'=>now()]);return (int)$row->id;}
        return $this->db->table('tz_stock_batches')->insertGetId(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'item_id'=>$itemId,'location_id'=>$locationId,'batch_number'=>$batch,'serial_number'=>$line['serial_number']??null,'expires_on'=>$line['expires_on']??null,'quantity_on_hand'=>$accepted,'quantity_reserved'=>0,'unit_cost'=>$line['unit_cost']??null,'status'=>'available','created_at'=>now(),'updated_at'=>now()]);
    }

    private function changeBalance(int $companyId,int $itemId,int $locationId,float $delta): void
    {
        $row=$this->db->table('tz_stock_balances')->where('company_id',$companyId)->where('item_id',$itemId)->where('location_id',$locationId)->lockForUpdate()->first();if(!$row){$id=$this->db->table('tz_stock_balances')->insertGetId(['company_id'=>$companyId,'item_id'=>$itemId,'location_id'=>$locationId,'quantity_on_hand'=>0,'quantity_reserved'=>0,'reorder_point'=>0,'reorder_quantity'=>0,'created_at'=>now(),'updated_at'=>now()]);$row=$this->db->table('tz_stock_balances')->where('id',$id)->lockForUpdate()->first();}
        $new=(float)$row->quantity_on_hand+$delta;$location=$this->db->table('tz_stock_locations')->where('id',$locationId)->where('company_id',$companyId)->first(['allow_negative_stock']);if($new<0&&!($location->allow_negative_stock??false)){throw new InvalidArgumentException('Operation would create negative stock.');}$this->db->table('tz_stock_balances')->where('id',$row->id)->update(['quantity_on_hand'=>$new,'updated_at'=>now()]);
    }

    private function insertMovement(int $companyId,int $itemId,?int $fromId,?int $toId,string $type,float $qty,string $reference,int $actorId,mixed $unitCost): void
    {
        $this->db->table('tz_stock_movements')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'item_id'=>$itemId,'from_location_id'=>$fromId,'to_location_id'=>$toId,'movement_type'=>$type,'quantity'=>$qty,'unit_cost'=>$unitCost,'reference'=>$reference,'occurred_at'=>now(),'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function purchaseOrder(int $companyId,string $publicId): array{$po=$this->byPublicId('tz_purchase_orders',$companyId,$publicId);$po['items']=$this->db->table('tz_purchase_order_items')->where('company_id',$companyId)->where('purchase_order_id',$po['id'])->get()->map(fn($r)=>(array)$r)->all();return $po;}
    private function goodsReceipt(int $companyId,string $publicId): array{$r=$this->byPublicId('tz_goods_receipts',$companyId,$publicId);$r['items']=$this->db->table('tz_goods_receipt_items')->where('company_id',$companyId)->where('goods_receipt_id',$r['id'])->get()->map(fn($x)=>(array)$x)->all();return $r;}
    private function transfer(int $companyId,string $publicId): array{$r=$this->byPublicId('tz_stock_transfers',$companyId,$publicId);$r['items']=$this->db->table('tz_stock_transfer_items')->where('company_id',$companyId)->where('transfer_id',$r['id'])->get()->map(fn($x)=>(array)$x)->all();return $r;}
    private function stockCount(int $companyId,string $publicId): array{$r=$this->byPublicId('tz_stock_counts',$companyId,$publicId);$r['items']=$this->db->table('tz_stock_count_items')->where('company_id',$companyId)->where('stock_count_id',$r['id'])->get()->map(fn($x)=>(array)$x)->all();return $r;}
    private function adjustment(int $companyId,string $publicId): array{$r=$this->byPublicId('tz_stock_adjustments',$companyId,$publicId);$r['items']=$this->db->table('tz_stock_adjustment_items')->where('company_id',$companyId)->where('adjustment_id',$r['id'])->get()->map(fn($x)=>(array)$x)->all();return $r;}
    private function record(string $table,string $publicId,int $companyId): object{$this->assertCompany($companyId);$q=$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId);if(in_array($table,['tz_suppliers','tz_purchase_orders','tz_inventory_items','tz_stock_locations','tz_inventory_categories'],true)){$q->whereNull('deleted_at');}$row=$q->first();if(!$row){throw new InvalidArgumentException("Referenced {$table} record is unavailable in the active company.");}return $row;}
    private function locked(string $table,string $publicId,int $companyId): object{$this->assertCompany($companyId);$row=$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId)->lockForUpdate()->first();if(!$row){throw new InvalidArgumentException('Record is unavailable in the active company.');}return $row;}
    private function byPublicId(string $table,int $companyId,string $publicId): array{$row=$this->db->table($table)->where('company_id',$companyId)->where('public_id',$publicId)->first();return $row?(array)$row:['public_id'=>$publicId];}
    private function assertActor(int $companyId,int $actorId):void{$this->assertCompany($companyId);if($actorId!==$this->actorId()){throw new \RuntimeException('Repository actor does not match active WorkCore actor.');}}
}
