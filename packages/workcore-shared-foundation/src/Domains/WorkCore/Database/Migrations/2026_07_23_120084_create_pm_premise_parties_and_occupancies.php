<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_party_roles')) {
            Schema::create('pm_premise_party_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->string('party_type', 80)->default('snapshot')->index();
                $table->unsignedBigInteger('party_id')->nullable()->index();
                $table->string('role', 80)->default('contact')->index();
                $table->string('display_name', 191);
                $table->string('email')->nullable();
                $table->string('phone', 80)->nullable();
                $table->string('organisation_name')->nullable();
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->json('permissions')->nullable();
                $table->string('source_type', 80)->nullable()->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->unique(['company_id', 'source_type', 'source_id'], 'pm_party_source_unique');
                $table->index(['company_id', 'premise_id', 'role'], 'pm_party_premise_role_index');
                $table->index(['company_id', 'party_type', 'party_id'], 'pm_party_reference_index');
            });
        }

        if (!Schema::hasTable('pm_premise_occupancies')) {
            Schema::create('pm_premise_occupancies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->string('party_type', 80)->default('snapshot')->index();
                $table->unsignedBigInteger('party_id')->nullable()->index();
                $table->string('display_name', 191);
                $table->string('occupancy_type', 80)->default('occupant')->index();
                $table->string('status', 40)->default('applicant')->index();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('actual_end_date')->nullable();
                $table->unsignedInteger('capacity_used')->default(1);
                $table->boolean('is_primary')->default(true);
                $table->string('agreement_reference')->nullable()->index();
                $table->string('billing_account_reference')->nullable()->index();
                $table->string('deposit_reference')->nullable()->index();
                $table->string('move_in_status', 80)->nullable();
                $table->string('move_out_status', 80)->nullable();
                $table->unsignedBigInteger('previous_occupancy_id')->nullable()->index();
                $table->text('transfer_reason')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->restrictOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('previous_occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_occupancy_premise_status_index');
                $table->index(['company_id', 'space_id', 'status'], 'pm_occupancy_space_status_index');
                $table->index(['company_id', 'party_type', 'party_id'], 'pm_occupancy_party_index');
            });
        }

        if (Schema::hasTable('pm_property_contacts')) {
            $now = now();
            DB::table('pm_property_contacts')->orderBy('id')->chunkById(200, function ($contacts) use ($now) {
                foreach ($contacts as $contact) {
                    DB::table('pm_premise_party_roles')->updateOrInsert(
                        [
                            'company_id' => $contact->company_id,
                            'source_type' => 'property_contact',
                            'source_id' => $contact->id,
                        ],
                        [
                            'user_id' => $contact->user_id ?? null,
                            'premise_id' => $contact->property_id,
                            'party_type' => 'snapshot',
                            'role' => $contact->role ?: 'contact',
                            'display_name' => $contact->name,
                            'email' => $contact->email,
                            'phone' => $contact->phone,
                            'organisation_name' => $contact->company,
                            'valid_from' => $contact->created_at ? substr((string) $contact->created_at, 0, 10) : null,
                            'is_primary' => false,
                            'metadata' => json_encode([
                                'notes' => $contact->notes,
                                'migrated_from' => 'pm_property_contacts',
                            ]),
                            'created_at' => $contact->created_at ?? $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_occupancies');
        Schema::dropIfExists('pm_premise_party_roles');
    }
};
