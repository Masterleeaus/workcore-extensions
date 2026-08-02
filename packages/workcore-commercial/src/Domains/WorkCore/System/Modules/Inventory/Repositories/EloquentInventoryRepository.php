<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Inventory\Contracts\InventoryRepositoryContract;
use App\Domains\WorkCore\System\Modules\Inventory\Services\InventoryQuantityPolicy;
use App\Domains\WorkCore\System\Modules\Inventory\Services\StockMovementPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentInventoryRepository extends TenantScopedRepository implements InventoryRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CompanyScopedReferenceValidator $references,
        private InventoryQuantityPolicy $quantities,
        private StockMovementPolicy $movements,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $stock = $this->db->table('tz_stock_balances')
            ->selectRaw('item_id, SUM(quantity_on_hand) as quantity_on_hand, SUM(quantity_reserved) as quantity_reserved, SUM(quantity_on_hand - quantity_reserved) as quantity_available')
            ->where('company_id', $companyId)
            ->groupBy('item_id');
        $query = $this->db->table('tz_inventory_items as items')
            ->leftJoinSub($stock, 'stock', 'stock.item_id', '=', 'items.id')
            ->where('items.company_id', $companyId)
            ->whereNull('items.deleted_at')
            ->select('items.*')
            ->selectRaw('COALESCE(stock.quantity_on_hand, 0) as quantity_on_hand')
            ->selectRaw('COALESCE(stock.quantity_reserved, 0) as quantity_reserved')
            ->selectRaw('COALESCE(stock.quantity_available, 0) as quantity_available');

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $builder) use ($q): void {
                $builder->where('items.name', 'like', "%{$q}%")
                    ->orWhere('items.sku', 'like', "%{$q}%")
                    ->orWhere('items.description', 'like', "%{$q}%");
            });
        }
        if (! empty($filters['item_type'])) {
            $query->where('items.item_type', $filters['item_type']);
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $query->where('items.active', filter_var($filters['active'], FILTER_VALIDATE_BOOL));
        }
        if ((bool) ($filters['low_stock'] ?? false)) {
            $query->whereRaw('COALESCE(stock.quantity_available, 0) <= items.default_reorder_point');
        }
        return $query->orderBy('items.name')->paginate(max(1, min($perPage, 100)));
    }

    public function locations(int $companyId, array $filters = []): array
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_stock_locations as locations')
            ->leftJoin('tz_premises as premises', 'premises.id', '=', 'locations.premises_id')
            ->leftJoin('tz_assets as assets', 'assets.id', '=', 'locations.asset_id')
            ->leftJoin('tz_workers as workers', 'workers.id', '=', 'locations.worker_id')
            ->where('locations.company_id', $companyId)
            ->whereNull('locations.deleted_at')
            ->select('locations.*', 'premises.public_id as premises_public_id', 'assets.public_id as asset_public_id', 'workers.public_id as worker_public_id');
        if (! empty($filters['location_type'])) {
            $query->where('locations.location_type', $filters['location_type']);
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null && $filters['active'] !== '') {
            $query->where('locations.active', filter_var($filters['active'], FILTER_VALIDATE_BOOL));
        }
        return $query->orderBy('locations.name')->get()->map(static fn ($row): array => (array) $row)->all();
    }

    public function createItem(array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $id = $this->db->table('tz_inventory_items')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'supply_item_public_id' => $data['supply_item_public_id'] ?? null,
            'sku' => $data['sku'],
            'name' => $data['name'],
            'item_type' => $data['item_type'] ?? 'material',
            'unit_of_measure' => $data['unit_of_measure'] ?? 'each',
            'track_stock' => (bool) ($data['track_stock'] ?? true),
            'lot_tracking' => (bool) ($data['lot_tracking'] ?? false),
            'serial_tracking' => (bool) ($data['serial_tracking'] ?? false),
            'active' => (bool) ($data['active'] ?? true),
            'default_unit_cost' => $data['default_unit_cost'] ?? null,
            'default_reorder_point' => $data['default_reorder_point'] ?? 0,
            'default_reorder_quantity' => $data['default_reorder_quantity'] ?? 0,
            'description' => $data['description'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->itemById($id);
    }

    public function createLocation(array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $premisesId = ! empty($data['premises_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_premises', (string) $data['premises_public_id'], 'premises_public_id') : null;
        $assetId = ! empty($data['asset_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_assets', (string) $data['asset_public_id'], 'asset_public_id') : null;
        $workerId = ! empty($data['worker_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id') : null;
        $parentId = ! empty($data['parent_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['parent_public_id'], 'parent_public_id') : null;
        $type = (string) ($data['location_type'] ?? 'storeroom');
        if (in_array($type, ['property_store', 'room'], true) && $premisesId === null) {
            throw new InvalidArgumentException('Property and room stock locations require a premises reference.');
        }
        if (in_array($type, ['vehicle', 'mobile_kit'], true) && $assetId === null) {
            throw new InvalidArgumentException('Vehicle and mobile-kit stock locations require an asset reference.');
        }
        if ($type === 'worker_stock' && $workerId === null) {
            throw new InvalidArgumentException('Worker stock locations require a worker reference.');
        }
        $id = $this->db->table('tz_stock_locations')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'premises_id' => $premisesId,
            'asset_id' => $assetId,
            'worker_id' => $workerId,
            'code' => $data['code'],
            'name' => $data['name'],
            'location_type' => $type,
            'active' => (bool) ($data['active'] ?? true),
            'allow_negative_stock' => (bool) ($data['allow_negative_stock'] ?? false),
            'location_description' => $data['location_description'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_stock_locations')->where('id', $id)->first();
    }

    public function recordMovement(string $itemPublicId, array $data, int $companyId, int $actorId): array
    {
        $item = $this->item($itemPublicId, $companyId);
        $type = (string) $data['movement_type'];
        $quantity = (float) $data['quantity'];
        $fromId = ! empty($data['from_location_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['from_location_public_id'], 'from_location_public_id') : null;
        $toId = ! empty($data['to_location_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['to_location_public_id'], 'to_location_public_id') : null;
        $this->movements->assertShape($type, $fromId ? (string) $fromId : null, $toId ? (string) $toId : null);
        $this->movements->assertQuantity($type, $quantity);
        $workOrderId = ! empty($data['work_order_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['work_order_public_id'], 'work_order_public_id') : null;
        $appointmentId = ! empty($data['appointment_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_appointments', (string) $data['appointment_public_id'], 'appointment_public_id') : null;
        $workerId = ! empty($data['worker_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id') : null;

        return $this->db->transaction(function () use ($item, $data, $companyId, $actorId, $type, $quantity, $fromId, $toId, $workOrderId, $appointmentId, $workerId): array {
            if ($fromId !== null) {
                $this->changeBalance($companyId, (int) $item->id, $fromId, -abs($quantity), false);
            }
            if ($toId !== null) {
                $delta = $type === 'adjustment' ? $quantity : abs($quantity);
                $this->changeBalance($companyId, (int) $item->id, $toId, $delta, false);
            }
            $id = $this->insertMovement($companyId, (int) $item->id, $fromId, $toId, null, $workOrderId, $appointmentId, $workerId, $type, $quantity, $data, $actorId);
            if ($workOrderId !== null && in_array($type, ['issue', 'consumption', 'waste', 'return'], true)) {
                $this->updateWorkOrderMaterial($companyId, $workOrderId, (int) $item->id, $fromId ?? $toId, null, $type, abs($quantity), $data['unit_cost'] ?? $item->default_unit_cost, $data['reason'] ?? null);
            }
            return (array) $this->db->table('tz_stock_movements')->where('id', $id)->first();
        }, 3);
    }

    public function reserve(string $itemPublicId, array $data, int $companyId, int $actorId): array
    {
        $item = $this->item($itemPublicId, $companyId);
        $locationId = $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['location_public_id'], 'location_public_id');
        $workOrderId = ! empty($data['work_order_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['work_order_public_id'], 'work_order_public_id') : null;
        $appointmentId = ! empty($data['appointment_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_appointments', (string) $data['appointment_public_id'], 'appointment_public_id') : null;
        if ($workOrderId === null && $appointmentId === null) {
            throw new InvalidArgumentException('A stock reservation requires a Work Order or Appointment.');
        }
        $quantity = (float) $data['quantity'];
        $this->quantities->assertPositive($quantity);

        return $this->db->transaction(function () use ($item, $locationId, $workOrderId, $appointmentId, $quantity, $data, $companyId, $actorId): array {
            $balance = $this->lockedBalance($companyId, (int) $item->id, $locationId);
            $this->quantities->assertAvailable((float) $balance->quantity_on_hand, (float) $balance->quantity_reserved, $quantity);
            $this->db->table('tz_stock_balances')->where('id', $balance->id)->update([
                'quantity_reserved' => (float) $balance->quantity_reserved + $quantity,
                'updated_at' => now(),
            ]);
            $id = $this->db->table('tz_stock_reservations')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'item_id' => $item->id,
                'location_id' => $locationId,
                'work_order_id' => $workOrderId,
                'appointment_id' => $appointmentId,
                'quantity' => $quantity,
                'consumed_quantity' => 0,
                'status' => 'active',
                'reserved_until' => $data['reserved_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reserved_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($workOrderId !== null) {
                $this->updateWorkOrderMaterial($companyId, $workOrderId, (int) $item->id, $locationId, $id, 'reserve', $quantity, $data['unit_cost'] ?? $item->default_unit_cost, $data['notes'] ?? null);
            }
            return (array) $this->db->table('tz_stock_reservations')->where('id', $id)->first();
        }, 3);
    }

    public function consumeReservation(string $reservationPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $quantity = (float) $data['quantity'];
        $this->quantities->assertPositive($quantity);
        $workerId = ! empty($data['worker_public_id']) ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id') : null;

        return $this->db->transaction(function () use ($reservationPublicId, $data, $companyId, $actorId, $quantity, $workerId): array {
            $reservation = $this->db->table('tz_stock_reservations')->where('company_id', $companyId)->where('public_id', $reservationPublicId)->lockForUpdate()->first();
            if (! $reservation || ! in_array($reservation->status, ['active', 'partially_consumed'], true)) {
                throw new InvalidArgumentException('Stock reservation is not active.');
            }
            $this->quantities->assertReservationConsumption((float) $reservation->quantity, (float) $reservation->consumed_quantity, $quantity);
            $balance = $this->lockedBalance($companyId, (int) $reservation->item_id, (int) $reservation->location_id);
            if ($quantity > (float) $balance->quantity_on_hand + 0.00001) {
                throw new InvalidArgumentException('Insufficient on-hand stock to consume the reservation.');
            }
            $newConsumed = (float) $reservation->consumed_quantity + $quantity;
            $status = $newConsumed + 0.00001 >= (float) $reservation->quantity ? 'consumed' : 'partially_consumed';
            $this->db->table('tz_stock_balances')->where('id', $balance->id)->update([
                'quantity_on_hand' => (float) $balance->quantity_on_hand - $quantity,
                'quantity_reserved' => max(0, (float) $balance->quantity_reserved - $quantity),
                'updated_at' => now(),
            ]);
            $this->db->table('tz_stock_reservations')->where('id', $reservation->id)->update([
                'consumed_quantity' => $newConsumed,
                'status' => $status,
                'updated_at' => now(),
            ]);
            $movementId = $this->insertMovement($companyId, (int) $reservation->item_id, (int) $reservation->location_id, null, (int) $reservation->id, $reservation->work_order_id ? (int) $reservation->work_order_id : null, $reservation->appointment_id ? (int) $reservation->appointment_id : null, $workerId, 'consumption', $quantity, $data, $actorId);
            if ($reservation->work_order_id) {
                $this->updateWorkOrderMaterial($companyId, (int) $reservation->work_order_id, (int) $reservation->item_id, (int) $reservation->location_id, (int) $reservation->id, 'consumption', $quantity, $data['unit_cost'] ?? null, $data['reason'] ?? null);
            }
            return [
                'reservation' => (array) $this->db->table('tz_stock_reservations')->where('id', $reservation->id)->first(),
                'movement' => (array) $this->db->table('tz_stock_movements')->where('id', $movementId)->first(),
            ];
        }, 3);
    }

    public function releaseReservation(string $reservationPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Releasing a stock reservation requires a reason.');
        }
        return $this->db->transaction(function () use ($reservationPublicId, $companyId, $actorId, $reason): array {
            $reservation = $this->db->table('tz_stock_reservations')->where('company_id', $companyId)->where('public_id', $reservationPublicId)->lockForUpdate()->first();
            if (! $reservation || ! in_array($reservation->status, ['active', 'partially_consumed'], true)) {
                throw new InvalidArgumentException('Stock reservation is not active.');
            }
            $remaining = $this->quantities->remaining((float) $reservation->quantity, (float) $reservation->consumed_quantity);
            $balance = $this->lockedBalance($companyId, (int) $reservation->item_id, (int) $reservation->location_id);
            $this->db->table('tz_stock_balances')->where('id', $balance->id)->update([
                'quantity_reserved' => max(0, (float) $balance->quantity_reserved - $remaining),
                'updated_at' => now(),
            ]);
            $this->db->table('tz_stock_reservations')->where('id', $reservation->id)->update([
                'status' => 'released',
                'released_at' => now(),
                'release_reason' => $reason,
                'released_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);
            if ($reservation->work_order_id) {
                $this->db->table('tz_work_order_materials')
                    ->where('company_id', $companyId)
                    ->where('reservation_id', $reservation->id)
                    ->update(['reserved_quantity' => max(0, (float) $reservation->quantity - (float) $reservation->consumed_quantity - $remaining), 'status' => 'released', 'updated_at' => now()]);
            }
            return (array) $this->db->table('tz_stock_reservations')->where('id', $reservation->id)->first();
        }, 3);
    }

    public function setReorderRule(string $itemPublicId, array $data, int $companyId, int $actorId): array
    {
        $item = $this->item($itemPublicId, $companyId);
        $locationId = $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['location_public_id'], 'location_public_id');
        $point = (float) $data['reorder_point'];
        $quantity = (float) $data['reorder_quantity'];
        if ($point < 0 || $quantity < 0) {
            throw new InvalidArgumentException('Reorder values cannot be negative.');
        }
        return $this->db->transaction(function () use ($companyId, $item, $locationId, $point, $quantity): array {
            $balance = $this->lockedBalance($companyId, (int) $item->id, $locationId);
            $this->db->table('tz_stock_balances')->where('id', $balance->id)->update([
                'reorder_point' => $point,
                'reorder_quantity' => $quantity,
                'updated_at' => now(),
            ]);
            return (array) $this->db->table('tz_stock_balances')->where('id', $balance->id)->first();
        }, 3);
    }

    private function item(string $publicId, int $companyId): object
    {
        $this->assertCompany($companyId);
        $item = $this->db->table('tz_inventory_items')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first();
        if (! $item) {
            throw new InvalidArgumentException('Inventory item does not belong to the active company.');
        }
        return $item;
    }

    private function itemById(int $id): array
    {
        $row = $this->db->table('tz_inventory_items')->where('id', $id)->first();
        return $row ? (array) $row : [];
    }

    private function lockedBalance(int $companyId, int $itemId, int $locationId): object
    {
        $query = fn () => $this->db->table('tz_stock_balances')->where('company_id', $companyId)->where('item_id', $itemId)->where('location_id', $locationId);
        $balance = $query()->lockForUpdate()->first();
        if (! $balance) {
            $this->db->table('tz_stock_balances')->insertOrIgnore([
                'company_id' => $companyId,
                'item_id' => $itemId,
                'location_id' => $locationId,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'reorder_point' => 0,
                'reorder_quantity' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $balance = $query()->lockForUpdate()->first();
        }
        if (! $balance) {
            throw new InvalidArgumentException('Unable to establish stock balance.');
        }
        return $balance;
    }

    private function changeBalance(int $companyId, int $itemId, int $locationId, float $delta, bool $reservation): void
    {
        $balance = $this->lockedBalance($companyId, $itemId, $locationId);
        $new = (float) $balance->quantity_on_hand + $delta;
        $location = $this->db->table('tz_stock_locations')->where('company_id', $companyId)->where('id', $locationId)->first(['allow_negative_stock']);
        if ($new < -0.00001 && ! (bool) ($location->allow_negative_stock ?? false)) {
            throw new InvalidArgumentException('Stock movement would create a negative on-hand balance.');
        }
        $this->db->table('tz_stock_balances')->where('id', $balance->id)->update(['quantity_on_hand' => $new, 'updated_at' => now()]);
    }

    private function insertMovement(int $companyId, int $itemId, ?int $fromId, ?int $toId, ?int $reservationId, ?int $workOrderId, ?int $appointmentId, ?int $workerId, string $type, float $quantity, array $data, int $actorId): int
    {
        return $this->db->table('tz_stock_movements')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'item_id' => $itemId,
            'from_location_id' => $fromId,
            'to_location_id' => $toId,
            'reservation_id' => $reservationId,
            'work_order_id' => $workOrderId,
            'appointment_id' => $appointmentId,
            'worker_id' => $workerId,
            'movement_type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference' => $data['reference'] ?? null,
            'reason' => $data['reason'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateWorkOrderMaterial(int $companyId, int $workOrderId, int $itemId, ?int $locationId, ?int $reservationId, string $operation, float $quantity, mixed $unitCost, ?string $notes): void
    {
        $match = ['company_id' => $companyId, 'work_order_id' => $workOrderId, 'item_id' => $itemId, 'location_id' => $locationId];
        $row = $this->db->table('tz_work_order_materials')->where($match)->lockForUpdate()->first();
        if (! $row) {
            $id = $this->db->table('tz_work_order_materials')->insertGetId($match + [
                'public_id' => (string) Str::ulid(),
                'reservation_id' => $reservationId,
                'planned_quantity' => 0,
                'reserved_quantity' => $operation === 'reserve' ? $quantity : 0,
                'used_quantity' => in_array($operation, ['issue', 'consumption'], true) ? $quantity : 0,
                'wasted_quantity' => $operation === 'waste' ? $quantity : 0,
                'returned_quantity' => $operation === 'return' ? $quantity : 0,
                'unit_cost' => $unitCost,
                'status' => $operation === 'reserve' ? 'reserved' : 'issued',
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = $this->db->table('tz_work_order_materials')->where('id', $id)->first();
        } else {
            $updates = ['updated_at' => now()];
            if ($reservationId !== null) { $updates['reservation_id'] = $reservationId; }
            if ($unitCost !== null) { $updates['unit_cost'] = $unitCost; }
            if ($operation === 'reserve') { $updates['reserved_quantity'] = (float) $row->reserved_quantity + $quantity; $updates['status'] = 'reserved'; }
            if (in_array($operation, ['issue', 'consumption'], true)) { $updates['used_quantity'] = (float) $row->used_quantity + $quantity; $updates['reserved_quantity'] = max(0, (float) $row->reserved_quantity - $quantity); $updates['status'] = 'consumed'; }
            if ($operation === 'waste') { $updates['wasted_quantity'] = (float) $row->wasted_quantity + $quantity; $updates['status'] = 'consumed'; }
            if ($operation === 'return') { $updates['returned_quantity'] = (float) $row->returned_quantity + $quantity; $updates['status'] = 'returned'; }
            $this->db->table('tz_work_order_materials')->where('id', $row->id)->update($updates);
        }
    }
}
