<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandPremises();
        $this->expandInventory();
        $this->createSupplyChain();
        $this->expandAssetWarrantyNotifications();
        $this->createFleet();
        $this->createRepairs();
    }

    private function expandPremises(): void
    {
        Schema::table('tz_premises', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_premises', 'premises_type')) {
                $table->string('premises_type', 60)->default('service_location')->after('name');
            }
            if (! Schema::hasColumn('tz_premises', 'external_reference')) {
                $table->string('external_reference', 150)->nullable()->after('premises_type');
            }
            if (! Schema::hasColumn('tz_premises', 'tags')) {
                $table->json('tags')->nullable()->after('service_requirements');
            }
            if (! Schema::hasColumn('tz_premises', 'operating_notes')) {
                $table->text('operating_notes')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('tz_premises', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('tz_premises', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_premises', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('approved_by_user_id');
            }
            if (! Schema::hasColumn('tz_premises', 'archived_by_user_id')) {
                $table->foreignId('archived_by_user_id')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_premises', 'archive_reason')) {
                $table->text('archive_reason')->nullable()->after('archived_by_user_id');
            }
        });

        if (! Schema::hasTable('tz_premises_spaces')) {
            Schema::create('tz_premises_spaces', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->string('space_type', 40);
                $table->string('code', 100)->nullable();
                $table->string('name', 200);
                $table->string('floor_label', 100)->nullable();
                $table->decimal('area_square_metres', 12, 3)->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->string('status', 30)->default('active');
                $table->json('service_attributes')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'premises_id', 'code'], 'tz_premises_space_code_unique');
                $table->index(['company_id', 'premises_id', 'space_type', 'status'], 'tz_premises_space_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_premises_contact_links')) {
            Schema::create('tz_premises_contact_links', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('tz_customer_contacts')->cascadeOnDelete();
                $table->string('role', 60)->default('site_contact');
                $table->boolean('primary')->default(false);
                $table->json('notification_preferences')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'premises_id', 'contact_id', 'role'], 'tz_premises_contact_role_unique');
            });
        }

        if (! Schema::hasTable('tz_premises_access_points')) {
            Schema::create('tz_premises_access_points', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
                $table->string('name', 180);
                $table->string('access_type', 40)->default('entry');
                $table->string('vault_secret_reference', 255)->nullable();
                $table->string('availability_status', 30)->default('available');
                $table->json('instructions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['company_id', 'premises_id', 'availability_status'], 'tz_premises_access_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_premises_hazards')) {
            Schema::create('tz_premises_hazards', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->string('hazard_type', 80);
                $table->string('severity', 30)->default('medium');
                $table->string('status', 30)->default('active');
                $table->text('description');
                $table->text('control_measures')->nullable();
                $table->json('required_credential_public_ids')->nullable();
                $table->json('evidence_file_references')->nullable();
                $table->date('review_due_on')->nullable();
                $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'premises_id', 'status', 'severity'], 'tz_premises_hazard_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_premises_service_windows')) {
            Schema::create('tz_premises_service_windows', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->unsignedTinyInteger('day_of_week');
                $table->time('starts_at');
                $table->time('ends_at');
                $table->string('timezone', 80)->default('Australia/Melbourne');
                $table->string('window_type', 40)->default('service');
                $table->boolean('active')->default(true);
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'premises_id', 'day_of_week', 'active'], 'tz_premises_window_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_premises_service_plans')) {
            Schema::create('tz_premises_service_plans', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->foreignId('service_id')->nullable()->constrained('tz_services')->nullOnDelete();
                $table->string('name', 200);
                $table->string('frequency_type', 40)->default('recurring');
                $table->unsignedInteger('interval_value')->nullable();
                $table->string('interval_unit', 30)->nullable();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->date('next_due_on')->nullable();
                $table->string('status', 30)->default('active');
                $table->json('requirements')->nullable();
                $table->json('task_template_public_ids')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['company_id', 'premises_id', 'status', 'next_due_on'], 'tz_premises_plan_due_idx');
            });
        }

        if (! Schema::hasTable('tz_premises_meter_readings')) {
            Schema::create('tz_premises_meter_readings', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
                $table->string('meter_reference', 120);
                $table->string('reading_type', 60);
                $table->decimal('reading_value', 20, 6);
                $table->string('unit', 40);
                $table->timestamp('read_at');
                $table->string('source', 40)->default('manual');
                $table->json('evidence_file_references')->nullable();
                $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['company_id', 'premises_id', 'meter_reference', 'read_at'], 'tz_premises_meter_history_idx');
            });
        }

        if (! Schema::hasTable('tz_premises_document_links')) {
            Schema::create('tz_premises_document_links', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->string('file_reference', 255);
                $table->string('document_type', 50);
                $table->string('title', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'premises_id', 'file_reference'], 'tz_premises_document_unique');
            });
        }

        if (! Schema::hasTable('tz_premises_identifiers')) {
            Schema::create('tz_premises_identifiers', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('premises_id')->constrained('tz_premises')->cascadeOnDelete();
                $table->foreignId('space_id')->nullable()->constrained('tz_premises_spaces')->nullOnDelete();
                $table->string('identifier_type', 32);
                $table->string('value', 255);
                $table->boolean('active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'identifier_type', 'value'], 'tz_premises_identifier_unique');
            });
        }
    }

    private function expandInventory(): void
    {
        if (! Schema::hasTable('tz_inventory_categories')) {
            Schema::create('tz_inventory_categories', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('tz_inventory_categories')->nullOnDelete();
                $table->string('code', 100);
                $table->string('name', 180);
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'code'], 'tz_inventory_category_code_unique');
            });
        }

        Schema::table('tz_inventory_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_inventory_items', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('company_id')->constrained('tz_inventory_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_inventory_items', 'barcode')) {
                $table->string('barcode', 150)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('tz_inventory_items', 'brand')) {
                $table->string('brand', 150)->nullable()->after('name');
            }
            if (! Schema::hasColumn('tz_inventory_items', 'manufacturer')) {
                $table->string('manufacturer', 150)->nullable()->after('brand');
            }
            if (! Schema::hasColumn('tz_inventory_items', 'lead_time_days')) {
                $table->unsignedInteger('lead_time_days')->nullable()->after('default_reorder_quantity');
            }
        });

        Schema::table('tz_stock_locations', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_stock_locations', 'address_line_1')) {
                $table->string('address_line_1')->nullable()->after('location_description');
                $table->string('address_line_2')->nullable()->after('address_line_1');
                $table->string('suburb')->nullable()->after('address_line_2');
                $table->string('state', 100)->nullable()->after('suburb');
                $table->string('postcode', 20)->nullable()->after('state');
                $table->string('country_code', 2)->default('AU')->after('postcode');
            }
            if (! Schema::hasColumn('tz_stock_locations', 'contact_name')) {
                $table->string('contact_name', 180)->nullable()->after('country_code');
                $table->string('contact_phone', 80)->nullable()->after('contact_name');
                $table->string('contact_email', 180)->nullable()->after('contact_phone');
            }
        });

        if (! Schema::hasTable('tz_stock_batches')) {
            Schema::create('tz_stock_batches', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('tz_inventory_items')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('tz_stock_locations')->cascadeOnDelete();
                $table->string('batch_number', 150);
                $table->string('serial_number', 150)->nullable();
                $table->date('manufactured_on')->nullable();
                $table->date('expires_on')->nullable();
                $table->decimal('quantity_on_hand', 18, 4)->default(0);
                $table->decimal('quantity_reserved', 18, 4)->default(0);
                $table->decimal('unit_cost', 14, 4)->nullable();
                $table->string('status', 30)->default('available');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'item_id', 'location_id', 'batch_number'], 'tz_stock_batch_unique');
                $table->index(['company_id', 'expires_on', 'status'], 'tz_stock_batch_expiry_idx');
            });
        }
    }

    private function createSupplyChain(): void
    {
        if (! Schema::hasTable('tz_suppliers')) {
            Schema::create('tz_suppliers', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->string('supplier_number', 100);
                $table->string('name', 200);
                $table->string('status', 30)->default('active');
                $table->string('contact_name', 180)->nullable();
                $table->string('email', 180)->nullable();
                $table->string('phone', 80)->nullable();
                $table->string('website', 255)->nullable();
                $table->string('tax_identifier', 100)->nullable();
                $table->string('payment_terms', 100)->nullable();
                $table->string('currency', 3)->default('AUD');
                $table->text('address')->nullable();
                $table->json('capabilities')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'supplier_number'], 'tz_supplier_number_unique');
                $table->index(['company_id', 'status', 'name'], 'tz_supplier_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_supplier_ratings')) {
            Schema::create('tz_supplier_ratings', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('tz_suppliers')->cascadeOnDelete();
                $table->unsignedTinyInteger('quality_rating');
                $table->unsignedTinyInteger('delivery_rating');
                $table->unsignedTinyInteger('service_rating');
                $table->text('notes')->nullable();
                $table->foreignId('rated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rated_at');
                $table->timestamps();
                $table->index(['company_id', 'supplier_id', 'rated_at'], 'tz_supplier_rating_history_idx');
            });
        }

        if (! Schema::hasTable('tz_purchase_orders')) {
            Schema::create('tz_purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('tz_suppliers')->restrictOnDelete();
                $table->foreignId('delivery_location_id')->nullable()->constrained('tz_stock_locations')->nullOnDelete();
                $table->string('order_number', 100);
                $table->string('status', 30)->default('draft');
                $table->string('currency', 3)->default('AUD');
                $table->date('ordered_on')->nullable();
                $table->date('expected_on')->nullable();
                $table->decimal('subtotal', 16, 2)->default(0);
                $table->decimal('tax_total', 16, 2)->default(0);
                $table->decimal('total', 16, 2)->default(0);
                $table->text('supplier_reference')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'order_number'], 'tz_purchase_order_number_unique');
                $table->index(['company_id', 'supplier_id', 'status'], 'tz_purchase_order_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_purchase_order_items')) {
            Schema::create('tz_purchase_order_items', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->constrained('tz_purchase_orders')->cascadeOnDelete();
                $table->foreignId('item_id')->nullable()->constrained('tz_inventory_items')->nullOnDelete();
                $table->string('description', 255);
                $table->decimal('ordered_quantity', 18, 4);
                $table->decimal('received_quantity', 18, 4)->default(0);
                $table->decimal('unit_cost', 14, 4);
                $table->decimal('tax_rate', 8, 4)->default(0);
                $table->decimal('line_total', 16, 2);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'purchase_order_id'], 'tz_purchase_order_item_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_goods_receipts')) {
            Schema::create('tz_goods_receipts', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('tz_purchase_orders')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('tz_suppliers')->nullOnDelete();
                $table->foreignId('location_id')->constrained('tz_stock_locations')->restrictOnDelete();
                $table->string('receipt_number', 100);
                $table->string('status', 30)->default('draft');
                $table->timestamp('received_at')->nullable();
                $table->string('delivery_reference', 160)->nullable();
                $table->json('evidence_file_references')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['company_id', 'receipt_number'], 'tz_goods_receipt_number_unique');
                $table->index(['company_id', 'purchase_order_id', 'status'], 'tz_goods_receipt_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_goods_receipt_items')) {
            Schema::create('tz_goods_receipt_items', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('goods_receipt_id')->constrained('tz_goods_receipts')->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->nullable()->constrained('tz_purchase_order_items')->nullOnDelete();
                $table->foreignId('item_id')->constrained('tz_inventory_items')->restrictOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('tz_stock_batches')->nullOnDelete();
                $table->decimal('received_quantity', 18, 4);
                $table->decimal('accepted_quantity', 18, 4);
                $table->decimal('rejected_quantity', 18, 4)->default(0);
                $table->decimal('unit_cost', 14, 4)->nullable();
                $table->string('serial_number', 150)->nullable();
                $table->date('expires_on')->nullable();
                $table->string('condition', 40)->default('accepted');
                $table->text('rejection_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'goods_receipt_id'], 'tz_goods_receipt_item_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_stock_transfers')) {
            Schema::create('tz_stock_transfers', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('from_location_id')->constrained('tz_stock_locations')->restrictOnDelete();
                $table->foreignId('to_location_id')->constrained('tz_stock_locations')->restrictOnDelete();
                $table->string('transfer_number', 100);
                $table->string('status', 30)->default('draft');
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('dispatched_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'transfer_number'], 'tz_stock_transfer_number_unique');
                $table->index(['company_id', 'status', 'requested_at'], 'tz_stock_transfer_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_stock_transfer_items')) {
            Schema::create('tz_stock_transfer_items', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('transfer_id')->constrained('tz_stock_transfers')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('tz_inventory_items')->restrictOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('tz_stock_batches')->nullOnDelete();
                $table->decimal('requested_quantity', 18, 4);
                $table->decimal('dispatched_quantity', 18, 4)->default(0);
                $table->decimal('received_quantity', 18, 4)->default(0);
                $table->timestamps();
                $table->unique(['company_id', 'transfer_id', 'item_id', 'batch_id'], 'tz_stock_transfer_item_unique');
            });
        }

        if (! Schema::hasTable('tz_stock_counts')) {
            Schema::create('tz_stock_counts', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('tz_stock_locations')->restrictOnDelete();
                $table->string('count_number', 100);
                $table->string('count_type', 40)->default('cycle');
                $table->string('status', 30)->default('draft');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'count_number'], 'tz_stock_count_number_unique');
                $table->index(['company_id', 'location_id', 'status'], 'tz_stock_count_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_stock_count_items')) {
            Schema::create('tz_stock_count_items', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('stock_count_id')->constrained('tz_stock_counts')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('tz_inventory_items')->restrictOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('tz_stock_batches')->nullOnDelete();
                $table->decimal('expected_quantity', 18, 4);
                $table->decimal('counted_quantity', 18, 4)->nullable();
                $table->decimal('variance_quantity', 18, 4)->nullable();
                $table->text('variance_reason')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'stock_count_id', 'item_id', 'batch_id'], 'tz_stock_count_item_unique');
            });
        }

        if (! Schema::hasTable('tz_stock_adjustments')) {
            Schema::create('tz_stock_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('tz_stock_locations')->restrictOnDelete();
                $table->foreignId('stock_count_id')->nullable()->constrained('tz_stock_counts')->nullOnDelete();
                $table->string('adjustment_number', 100);
                $table->string('reason_code', 60);
                $table->string('status', 30)->default('draft');
                $table->text('reason')->nullable();
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'adjustment_number'], 'tz_stock_adjustment_number_unique');
            });
        }

        if (! Schema::hasTable('tz_stock_adjustment_items')) {
            Schema::create('tz_stock_adjustment_items', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('adjustment_id')->constrained('tz_stock_adjustments')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('tz_inventory_items')->restrictOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('tz_stock_batches')->nullOnDelete();
                $table->decimal('quantity_delta', 18, 4);
                $table->decimal('unit_cost', 14, 4)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    private function expandAssetWarrantyNotifications(): void
    {
        Schema::table('tz_asset_warranties', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_asset_warranties', 'expiry_alerted_at')) {
                $table->timestamp('expiry_alerted_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('tz_asset_warranties', 'expired_event_at')) {
                $table->timestamp('expired_event_at')->nullable()->after('expiry_alerted_at');
            }
        });
    }

    private function createFleet(): void
    {
        if (! Schema::hasTable('tz_fleet_vehicles')) {
            Schema::create('tz_fleet_vehicles', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('tz_assets')->restrictOnDelete();
                $table->foreignId('home_premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
                $table->string('fleet_number', 100);
                $table->string('registration_number', 100);
                $table->string('vin', 100)->nullable();
                $table->string('make', 120)->nullable();
                $table->string('model', 120)->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('vehicle_type', 60)->default('service_vehicle');
                $table->string('fuel_type', 40)->nullable();
                $table->string('status', 30)->default('available');
                $table->decimal('odometer', 14, 2)->default(0);
                $table->string('odometer_unit', 12)->default('km');
                $table->date('registration_expires_on')->nullable();
                $table->date('insurance_expires_on')->nullable();
                $table->date('next_service_due_on')->nullable();
                $table->decimal('next_service_due_odometer', 14, 2)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'asset_id'], 'tz_fleet_asset_unique');
                $table->unique(['company_id', 'fleet_number'], 'tz_fleet_number_unique');
                $table->unique(['company_id', 'registration_number'], 'tz_fleet_registration_unique');
                $table->index(['company_id', 'status', 'vehicle_type'], 'tz_fleet_vehicle_lookup_idx');
            });
        }

        if (! Schema::hasTable('tz_fleet_mileage_logs')) {
            Schema::create('tz_fleet_mileage_logs', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('tz_fleet_vehicles')->cascadeOnDelete();
                $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
                $table->decimal('odometer_start', 14, 2)->nullable();
                $table->decimal('odometer_end', 14, 2);
                $table->decimal('distance', 14, 2)->nullable();
                $table->timestamp('logged_at');
                $table->string('source', 40)->default('manual');
                $table->text('notes')->nullable();
                $table->json('evidence_file_references')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'vehicle_id', 'logged_at'], 'tz_fleet_mileage_history_idx');
            });
        }

        if (! Schema::hasTable('tz_fleet_assignments')) {
            Schema::create('tz_fleet_assignments', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('vehicle_id')->constrained('tz_fleet_vehicles')->cascadeOnDelete();
                $table->foreignId('asset_assignment_id')->constrained('tz_asset_assignments')->restrictOnDelete();
                $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
                $table->string('assignment_type', 40)->default('worker');
                $table->timestamp('starts_at');
                $table->timestamp('expected_return_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->string('status', 30)->default('active');
                $table->string('active_lock', 20)->nullable();
                $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'vehicle_id', 'active_lock'], 'tz_fleet_active_assignment_unique');
                $table->index(['company_id', 'worker_id', 'status'], 'tz_fleet_assignment_worker_idx');
            });
        }
    }

    private function createRepairs(): void
    {
        if (! Schema::hasTable('tz_repair_order_templates')) {
            Schema::create('tz_repair_order_templates', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->string('code', 100);
                $table->string('name', 200);
                $table->string('repair_type', 60)->default('general');
                $table->json('default_tasks')->nullable();
                $table->json('required_parts')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['company_id', 'code'], 'tz_repair_template_code_unique');
            });
        }

        if (! Schema::hasTable('tz_repair_orders')) {
            Schema::create('tz_repair_orders', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('tz_assets')->restrictOnDelete();
                $table->foreignId('vehicle_id')->nullable()->constrained('tz_fleet_vehicles')->nullOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('tz_repair_order_templates')->nullOnDelete();
                $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
                $table->foreignId('maintenance_record_id')->nullable()->constrained('tz_asset_maintenance_records')->nullOnDelete();
                $table->string('repair_number', 100);
                $table->string('status', 30)->default('reported');
                $table->string('priority', 30)->default('normal');
                $table->text('fault_description');
                $table->text('diagnosis')->nullable();
                $table->text('repair_actions')->nullable();
                $table->json('parts_references')->nullable();
                $table->decimal('estimated_cost', 14, 2)->nullable();
                $table->decimal('actual_cost', 14, 2)->nullable();
                $table->timestamp('reported_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('returned_to_service_at')->nullable();
                $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
                $table->foreignId('return_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('evidence_file_references')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'repair_number'], 'tz_repair_order_number_unique');
                $table->index(['company_id', 'asset_id', 'status'], 'tz_repair_asset_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_repair_orders');
        Schema::dropIfExists('tz_repair_order_templates');
        Schema::dropIfExists('tz_fleet_assignments');
        Schema::dropIfExists('tz_fleet_mileage_logs');
        Schema::dropIfExists('tz_fleet_vehicles');
        Schema::dropIfExists('tz_stock_adjustment_items');
        Schema::dropIfExists('tz_stock_adjustments');
        Schema::dropIfExists('tz_stock_count_items');
        Schema::dropIfExists('tz_stock_counts');
        Schema::dropIfExists('tz_stock_transfer_items');
        Schema::dropIfExists('tz_stock_transfers');
        Schema::dropIfExists('tz_goods_receipt_items');
        Schema::dropIfExists('tz_goods_receipts');
        Schema::dropIfExists('tz_purchase_order_items');
        Schema::dropIfExists('tz_purchase_orders');
        Schema::dropIfExists('tz_supplier_ratings');
        Schema::dropIfExists('tz_suppliers');
        Schema::dropIfExists('tz_stock_batches');

        Schema::table('tz_asset_warranties', function (Blueprint $table): void {
            foreach (['expiry_alerted_at', 'expired_event_at'] as $column) {
                if (Schema::hasColumn('tz_asset_warranties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('tz_stock_locations', function (Blueprint $table): void {
            foreach (['address_line_1','address_line_2','suburb','state','postcode','country_code','contact_name','contact_phone','contact_email'] as $column) {
                if (Schema::hasColumn('tz_stock_locations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('tz_inventory_items', function (Blueprint $table): void {
            if (Schema::hasColumn('tz_inventory_items', 'category_id')) {
                $table->dropForeign(['category_id']);
            }
            foreach (['category_id','barcode','brand','manufacturer','lead_time_days'] as $column) {
                if (Schema::hasColumn('tz_inventory_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('tz_inventory_categories');

        Schema::dropIfExists('tz_premises_identifiers');
        Schema::dropIfExists('tz_premises_document_links');
        Schema::dropIfExists('tz_premises_meter_readings');
        Schema::dropIfExists('tz_premises_service_plans');
        Schema::dropIfExists('tz_premises_service_windows');
        Schema::dropIfExists('tz_premises_hazards');
        Schema::dropIfExists('tz_premises_access_points');
        Schema::dropIfExists('tz_premises_contact_links');
        Schema::dropIfExists('tz_premises_spaces');

        Schema::table('tz_premises', function (Blueprint $table): void {
            if (Schema::hasColumn('tz_premises', 'approved_by_user_id')) {
                $table->dropForeign(['approved_by_user_id']);
            }
            if (Schema::hasColumn('tz_premises', 'archived_by_user_id')) {
                $table->dropForeign(['archived_by_user_id']);
            }
            foreach (['premises_type','external_reference','tags','operating_notes','approved_at','approved_by_user_id','archived_at','archived_by_user_id','archive_reason'] as $column) {
                if (Schema::hasColumn('tz_premises', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
