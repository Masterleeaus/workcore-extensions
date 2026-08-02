<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pm_accommodation_rate_plans')) {
            Schema::create('pm_accommodation_rate_plans', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->nullable()->index();
                $table->unsignedBigInteger('business_line_id')->nullable()->index();
                $table->string('code', 100);
                $table->string('name', 191);
                $table->string('pricing_basis', 40)->default('night')->index();
                $table->char('currency', 3)->default('AUD');
                $table->decimal('base_amount', 14, 4)->default(0);
                $table->unsignedInteger('minimum_nights')->default(1);
                $table->unsignedInteger('maximum_nights')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->json('restrictions')->nullable();
                $table->json('cancellation_policy')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_rate_public_unique');
                $table->unique(['company_id', 'code'], 'pm_accom_rate_code_unique');
                $table->index(['company_id', 'premise_id', 'active'], 'pm_accom_rate_scope_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_reservations')) {
            Schema::create('pm_accommodation_reservations', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('primary_space_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('business_line_id')->nullable()->index();
                $table->unsignedBigInteger('rate_plan_id')->nullable()->index();
                $table->string('reservation_type', 60)->default('short_stay')->index();
                $table->string('status', 40)->default('draft')->index();
                $table->string('source_channel', 60)->default('direct')->index();
                $table->string('external_reference', 191)->nullable()->index();
                $table->string('primary_guest_name', 191);
                $table->string('primary_guest_email')->nullable();
                $table->string('primary_guest_phone', 80)->nullable();
                $table->dateTime('arrival_at')->index();
                $table->dateTime('departure_at')->index();
                $table->dateTime('booked_at')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->unsignedInteger('adults')->default(1);
                $table->unsignedInteger('children')->default(0);
                $table->unsignedInteger('capacity')->default(1);
                $table->char('currency', 3)->default('AUD');
                $table->decimal('subtotal_amount', 14, 4)->default(0);
                $table->decimal('tax_amount', 14, 4)->default(0);
                $table->decimal('total_amount', 14, 4)->default(0);
                $table->decimal('deposit_amount', 14, 4)->default(0);
                $table->decimal('balance_amount', 14, 4)->default(0);
                $table->text('cancellation_reason')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->restrictOnDelete();
                $table->foreign('primary_space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->foreign('rate_plan_id')->references('id')->on('pm_accommodation_rate_plans')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_res_public_unique');
                $table->unique(['company_id', 'source_channel', 'external_reference'], 'pm_accom_res_external_unique');
                $table->index(['company_id', 'premise_id', 'status', 'arrival_at'], 'pm_accom_res_arrival_index');
                $table->index(['company_id', 'premise_id', 'status', 'departure_at'], 'pm_accom_res_depart_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_reservation_spaces')) {
            Schema::create('pm_accommodation_reservation_spaces', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('reservation_id')->index();
                $table->unsignedBigInteger('space_id')->index();
                $table->unsignedBigInteger('rate_plan_id')->nullable()->index();
                $table->dateTime('arrival_at')->index();
                $table->dateTime('departure_at')->index();
                $table->unsignedInteger('capacity')->default(1);
                $table->decimal('unit_amount', 14, 4)->default(0);
                $table->decimal('tax_amount', 14, 4)->default(0);
                $table->decimal('total_amount', 14, 4)->default(0);
                $table->string('status', 40)->default('reserved')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('reservation_id')->references('id')->on('pm_accommodation_reservations')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->restrictOnDelete();
                $table->foreign('rate_plan_id')->references('id')->on('pm_accommodation_rate_plans')->nullOnDelete();
                $table->unique(['reservation_id', 'space_id'], 'pm_accom_res_space_unique');
                $table->index(['company_id', 'space_id', 'status', 'arrival_at'], 'pm_accom_space_arrival_index');
                $table->index(['company_id', 'space_id', 'status', 'departure_at'], 'pm_accom_space_depart_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_guests')) {
            Schema::create('pm_accommodation_guests', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('reservation_id')->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->string('guest_type', 60)->default('guest')->index();
                $table->string('display_name', 191);
                $table->string('email')->nullable();
                $table->string('phone', 80)->nullable();
                $table->date('date_of_birth')->nullable();
                $table->boolean('is_primary')->default(false)->index();
                $table->text('accessibility_requirements')->nullable();
                $table->json('emergency_contact')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('reservation_id')->references('id')->on('pm_accommodation_reservations')->cascadeOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_guest_public_unique');
                $table->index(['company_id', 'reservation_id', 'is_primary'], 'pm_accom_guest_primary_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_stays')) {
            Schema::create('pm_accommodation_stays', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('reservation_id')->index();
                $table->unsignedBigInteger('guest_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('access_authorisation_id')->nullable()->index();
                $table->string('status', 40)->default('expected')->index();
                $table->dateTime('expected_arrival_at');
                $table->dateTime('expected_departure_at');
                $table->dateTime('checked_in_at')->nullable()->index();
                $table->dateTime('checked_out_at')->nullable()->index();
                $table->unsignedBigInteger('checked_in_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('checked_out_by_user_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('reservation_id')->references('id')->on('pm_accommodation_reservations')->restrictOnDelete();
                $table->foreign('guest_id')->references('id')->on('pm_accommodation_guests')->restrictOnDelete();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->restrictOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->restrictOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->foreign('access_authorisation_id')->references('id')->on('pm_premise_access_authorisations')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_stay_public_unique');
                $table->unique(['company_id', 'reservation_id', 'space_id'], 'pm_accom_stay_res_space_unique');
                $table->index(['company_id', 'premise_id', 'status'], 'pm_accom_stay_status_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_housekeeping_tasks')) {
            Schema::create('pm_accommodation_housekeeping_tasks', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->index();
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->unsignedBigInteger('stay_id')->nullable()->index();
                $table->unsignedBigInteger('work_order_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_worker_id')->nullable()->index();
                $table->unsignedBigInteger('inspected_by_user_id')->nullable()->index();
                $table->string('task_type', 60)->default('turnover')->index();
                $table->string('status', 40)->default('dirty')->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->dateTime('due_at')->nullable()->index();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('inspected_at')->nullable();
                $table->string('condition_before', 80)->nullable();
                $table->string('condition_after', 80)->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->restrictOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->restrictOnDelete();
                $table->foreign('reservation_id')->references('id')->on('pm_accommodation_reservations')->nullOnDelete();
                $table->foreign('stay_id')->references('id')->on('pm_accommodation_stays')->nullOnDelete();
                $table->foreign('work_order_id')->references('id')->on('tz_work_orders')->nullOnDelete();
                $table->foreign('assigned_worker_id')->references('id')->on('tz_workers')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_house_public_unique');
                $table->index(['company_id', 'premise_id', 'status', 'due_at'], 'pm_accom_house_board_index');
                $table->index(['company_id', 'space_id', 'status'], 'pm_accom_house_space_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_charges')) {
            Schema::create('pm_accommodation_charges', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('reservation_id')->index();
                $table->unsignedBigInteger('stay_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->string('charge_type', 60)->default('accommodation')->index();
                $table->string('description', 191);
                $table->decimal('quantity', 12, 4)->default(1);
                $table->decimal('unit_amount', 14, 4)->default(0);
                $table->decimal('tax_amount', 14, 4)->default(0);
                $table->decimal('total_amount', 14, 4)->default(0);
                $table->char('currency', 3)->default('AUD');
                $table->string('status', 40)->default('pending')->index();
                $table->dateTime('occurred_at')->index();
                $table->dateTime('posted_at')->nullable();
                $table->dateTime('voided_at')->nullable();
                $table->string('finance_provider', 80)->nullable()->index();
                $table->string('finance_external_id', 191)->nullable()->index();
                $table->string('finance_reference', 191)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('reservation_id')->references('id')->on('pm_accommodation_reservations')->restrictOnDelete();
                $table->foreign('stay_id')->references('id')->on('pm_accommodation_stays')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_charge_public_unique');
                $table->index(['company_id', 'reservation_id', 'status'], 'pm_accom_charge_folio_index');
            });
        }

        if (! Schema::hasTable('pm_accommodation_channel_references')) {
            Schema::create('pm_accommodation_channel_references', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->nullable()->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->string('channel', 60)->index();
                $table->string('entity_type', 60)->index();
                $table->string('external_id', 191);
                $table->string('external_url', 500)->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->text('sync_cursor')->nullable();
                $table->dateTime('last_synced_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->nullOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->foreign('reservation_id')->references('id')->on('pm_accommodation_reservations')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'pm_accom_channel_public_unique');
                $table->unique(['company_id', 'channel', 'entity_type', 'external_id'], 'pm_accom_channel_external_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_accommodation_channel_references');
        Schema::dropIfExists('pm_accommodation_charges');
        Schema::dropIfExists('pm_accommodation_housekeeping_tasks');
        Schema::dropIfExists('pm_accommodation_stays');
        Schema::dropIfExists('pm_accommodation_guests');
        Schema::dropIfExists('pm_accommodation_reservation_spaces');
        Schema::dropIfExists('pm_accommodation_reservations');
        Schema::dropIfExists('pm_accommodation_rate_plans');
    }
};
