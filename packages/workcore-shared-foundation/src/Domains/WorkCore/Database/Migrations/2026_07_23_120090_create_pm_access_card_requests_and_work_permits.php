<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_access_card_requests')) {
            Schema::create('pm_premise_access_card_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('requester_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_security_access_card_id')->nullable()->index();
                $table->string('request_number', 64);
                $table->string('status', 40)->default('draft')->index();
                $table->string('requested_for_name')->nullable();
                $table->string('requested_for_contact')->nullable();
                $table->string('purpose')->nullable();
                $table->timestamp('expected_return_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedBigInteger('decided_by_user_id')->nullable()->index();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_notes')->nullable();
                $table->string('fee_reference')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'request_number'], 'pm_access_card_request_number_unique');
                $table->unique(['company_id', 'legacy_security_access_card_id'], 'pm_access_card_legacy_unique');
                $table->index(['company_id', 'premise_id', 'status'], 'pm_access_card_request_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_access_card_request_items')) {
            Schema::create('pm_premise_access_card_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('access_card_request_id')->index();
                $table->unsignedBigInteger('holder_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('access_item_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_security_access_card_item_id')->nullable()->index();
                $table->string('label');
                $table->string('holder_display_name')->nullable();
                $table->string('status', 30)->default('requested')->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'legacy_security_access_card_item_id'], 'pm_access_card_item_legacy_unique');
                $table->index(['company_id', 'access_card_request_id', 'status'], 'pm_access_card_item_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_work_permits')) {
            Schema::create('pm_premise_work_permits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('applicant_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_security_work_permit_id')->nullable()->index();
                $table->string('permit_number', 64);
                $table->string('title');
                $table->string('contractor_name');
                $table->text('contractor_address')->nullable();
                $table->string('project_manager_name')->nullable();
                $table->string('site_coordinator_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('permit_type', 60)->default('maintenance')->index();
                $table->json('work_categories')->nullable();
                $table->text('work_description');
                $table->string('risk_level', 20)->default('medium')->index();
                $table->json('hazards')->nullable();
                $table->json('controls')->nullable();
                $table->timestamp('valid_from');
                $table->timestamp('valid_until');
                $table->string('status', 40)->default('draft')->index();
                $table->boolean('induction_required')->default(false);
                $table->timestamp('induction_completed_at')->nullable();
                $table->string('insurance_reference')->nullable();
                $table->string('licence_reference')->nullable();
                $table->text('conditions')->nullable();
                $table->text('validation_remarks')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->json('evidence_document_ids')->nullable();
                $table->string('workcore_linked_module')->nullable();
                $table->string('workcore_linked_id')->nullable();
                $table->string('workcore_reference')->nullable();
                $table->string('workcore_status')->nullable();
                $table->timestamp('workcore_linked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'permit_number'], 'pm_work_permit_number_unique');
                $table->unique(['company_id', 'legacy_security_work_permit_id'], 'pm_work_permit_legacy_unique');
                $table->index(['company_id', 'premise_id', 'status'], 'pm_work_permit_status_idx');
                $table->index(['company_id', 'valid_from', 'valid_until'], 'pm_work_permit_validity_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_work_permit_approvals')) {
            Schema::create('pm_premise_work_permit_approvals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('work_permit_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('stage', 40)->index();
                $table->string('decision', 30)->index();
                $table->text('remarks')->nullable();
                $table->json('evidence_document_ids')->nullable();
                $table->timestamp('decided_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'work_permit_id', 'stage'], 'pm_work_permit_approval_stage_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_work_permit_approvals');
        Schema::dropIfExists('pm_premise_work_permits');
        Schema::dropIfExists('pm_premise_access_card_request_items');
        Schema::dropIfExists('pm_premise_access_card_requests');
    }
};
