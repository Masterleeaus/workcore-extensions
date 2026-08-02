<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_workflows')) {
            Schema::create('pm_premise_workflows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('incident_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->string('profile_key', 80)->index();
                $table->string('template_key', 120)->index();
                $table->string('subject_type', 40)->nullable()->index();
                $table->string('title');
                $table->string('status', 30)->default('active')->index();
                $table->string('current_stage_key', 120)->nullable();
                $table->json('stages_snapshot');
                $table->json('completed_stage_keys')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->foreign('incident_id')->references('id')->on('pm_premise_incidents')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_workflow_premise_status_idx');
                $table->index(['company_id', 'template_key', 'status'], 'pm_workflow_template_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_workflows');
    }
};
