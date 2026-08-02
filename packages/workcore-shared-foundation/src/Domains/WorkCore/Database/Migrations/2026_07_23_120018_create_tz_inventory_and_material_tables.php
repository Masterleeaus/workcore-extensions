<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->char('supply_item_public_id', 26)->nullable()->comment('Optional Titan Supply canonical item reference.');
            $table->string('sku', 100);
            $table->string('name', 200);
            $table->string('item_type', 50)->default('material');
            $table->string('unit_of_measure', 40)->default('each');
            $table->boolean('track_stock')->default(true);
            $table->boolean('lot_tracking')->default(false);
            $table->boolean('serial_tracking')->default(false);
            $table->boolean('active')->default(true);
            $table->decimal('default_unit_cost', 14, 4)->nullable();
            $table->decimal('default_reorder_point', 14, 4)->default(0);
            $table->decimal('default_reorder_quantity', 14, 4)->default(0);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sku'], 'tz_inventory_item_company_sku_unique');
            $table->index(['company_id', 'item_type', 'active']);
            $table->index(['company_id', 'supply_item_public_id']);
        });

        Schema::create('tz_stock_locations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tz_stock_locations')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->string('location_type', 50)->default('storeroom');
            $table->boolean('active')->default(true);
            $table->boolean('allow_negative_stock')->default(false);
            $table->string('location_description', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code'], 'tz_stock_location_company_code_unique');
            $table->index(['company_id', 'location_type', 'active']);
            $table->index(['company_id', 'premises_id']);
            $table->index(['company_id', 'asset_id']);
        });

        Schema::create('tz_stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('tz_inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('tz_stock_locations')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('reorder_quantity', 18, 4)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'item_id', 'location_id'], 'tz_stock_balance_company_item_location_unique');
            $table->index(['company_id', 'location_id']);
            $table->index(['company_id', 'item_id']);
        });

        Schema::create('tz_stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('tz_inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('tz_stock_locations')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('consumed_quantity', 18, 4)->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reserved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'item_id', 'location_id', 'status'], 'tz_stock_res_company_item_location_status_idx');
            $table->index(['company_id', 'work_order_id', 'status']);
        });

        Schema::create('tz_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('tz_inventory_items')->cascadeOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('tz_stock_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('tz_stock_locations')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('tz_stock_reservations')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('movement_type', 30);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('reference', 150)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'item_id', 'occurred_at']);
            $table->index(['company_id', 'work_order_id']);
            $table->index(['company_id', 'from_location_id', 'occurred_at']);
            $table->index(['company_id', 'to_location_id', 'occurred_at']);
        });

        Schema::create('tz_work_order_materials', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('tz_inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('tz_stock_locations')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('tz_stock_reservations')->nullOnDelete();
            $table->decimal('planned_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('used_quantity', 18, 4)->default(0);
            $table->decimal('wasted_quantity', 18, 4)->default(0);
            $table->decimal('returned_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->string('status', 30)->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'work_order_id', 'item_id', 'location_id'], 'tz_wo_material_company_order_item_location_unique');
            $table->index(['company_id', 'work_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_work_order_materials');
        Schema::dropIfExists('tz_stock_movements');
        Schema::dropIfExists('tz_stock_reservations');
        Schema::dropIfExists('tz_stock_balances');
        Schema::dropIfExists('tz_stock_locations');
        Schema::dropIfExists('tz_inventory_items');
    }
};
