<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_access_items')) {
            Schema::create('pm_premise_access_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_property_key_id')->nullable()->index();
                $table->string('item_type', 50);
                $table->string('label');
                $table->string('identifier')->nullable();
                $table->text('secret_value')->nullable();
                $table->string('status', 40)->default('available')->index();
                $table->unsignedBigInteger('current_party_role_id')->nullable()->index();
                $table->string('current_holder_name')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('expected_return_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('location')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'legacy_property_key_id'], 'pm_access_legacy_key_unique');
                $table->index(['company_id', 'premise_id', 'status'], 'pm_access_premise_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_access_events')) {
            Schema::create('pm_premise_access_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('access_item_id')->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->string('event_type', 40)->index();
                $table->string('holder_display_name')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamp('expected_return_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pm_premise_access_authorisations')) {
            Schema::create('pm_premise_access_authorisations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->string('subject_name');
                $table->string('purpose', 80)->nullable();
                $table->string('access_scope', 80)->default('premise');
                $table->string('status', 30)->default('active')->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_access_auth_premise_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_condition_records')) {
            Schema::create('pm_premise_condition_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('comparison_record_id')->nullable()->index();
                $table->string('condition_type', 60)->index();
                $table->string('status', 30)->default('draft')->index();
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->decimal('score', 6, 2)->nullable();
                $table->text('summary')->nullable();
                $table->json('findings')->nullable();
                $table->json('actions')->nullable();
                $table->json('evidence_document_ids')->nullable();
                $table->string('external_reference')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'premise_id', 'condition_type'], 'pm_condition_premise_type_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_incidents')) {
            Schema::create('pm_premise_incidents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('reported_by_party_role_id')->nullable()->index();
                $table->string('incident_type', 60)->index();
                $table->string('severity', 20)->default('medium')->index();
                $table->string('status', 30)->default('open')->index();
                $table->string('privacy_level', 20)->default('standard')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamp('reported_at');
                $table->timestamp('resolved_at')->nullable();
                $table->string('workcore_record_type', 60)->nullable();
                $table->string('workcore_record_id')->nullable();
                $table->string('workcore_reference')->nullable();
                $table->string('workcore_status', 60)->nullable();
                $table->timestamp('workcore_linked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_incident_premise_status_idx');
            });
        }

        $this->migrateLegacyKeys();
        $this->migrateLegacyPremiseAccess();
    }

    private function migrateLegacyKeys(): void
    {
        if (!Schema::hasTable('pm_property_keys') || !Schema::hasTable('pm_premise_access_items')) {
            return;
        }

        DB::table('pm_property_keys')->orderBy('id')->chunkById(100, function ($keys): void {
            foreach ($keys as $key) {
                $secret = null;
                if (!empty($key->code)) {
                    try {
                        $secret = Crypt::encryptString((string) $key->code);
                    } catch (Throwable) {
                        $secret = null;
                    }
                }

                DB::table('pm_premise_access_items')->updateOrInsert(
                    ['company_id' => $key->company_id, 'legacy_property_key_id' => $key->id],
                    [
                        'user_id' => $key->user_id ?? null,
                        'premise_id' => $key->property_id,
                        'item_type' => $this->normaliseLegacyType((string) $key->type),
                        'label' => $key->type ?: 'Legacy access item',
                        'identifier' => null,
                        'secret_value' => $secret,
                        'status' => 'available',
                        'location' => $key->location,
                        'notes' => $key->notes,
                        'metadata' => json_encode(['legacy_source' => 'pm_property_keys']),
                        'created_at' => $key->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }

    private function migrateLegacyPremiseAccess(): void
    {
        if (!Schema::hasTable('premise_access') || !Schema::hasTable('pm_premise_access_items')) {
            return;
        }

        $mapping = [
            'key_code' => ['physical_key', 'Legacy key code'],
            'alarm_code' => ['alarm_code', 'Alarm code'],
            'gate_code' => ['gate_code', 'Gate code'],
            'intercom_code' => ['intercom_code', 'Intercom code'],
        ];

        foreach (DB::table('premise_access')->get() as $record) {
            foreach ($mapping as $column => [$type, $label]) {
                if (empty($record->{$column})) {
                    continue;
                }
                try {
                    $secret = Crypt::encryptString((string) $record->{$column});
                } catch (Throwable) {
                    $secret = null;
                }
                $identifier = 'legacy-premise-access:' . $record->id . ':' . $column;
                DB::table('pm_premise_access_items')->updateOrInsert(
                    ['company_id' => $record->company_id, 'identifier' => $identifier],
                    [
                        'premise_id' => $record->premise_id,
                        'item_type' => $type,
                        'label' => $label,
                        'secret_value' => $secret,
                        'status' => 'available',
                        'location' => null,
                        'notes' => $record->special_notes ?? null,
                        'metadata' => json_encode(['legacy_source' => 'premise_access', 'legacy_column' => $column]),
                        'created_at' => $record->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function normaliseLegacyType(string $type): string
    {
        $value = strtolower(trim($type));
        return match (true) {
            str_contains($value, 'card') => 'access_card',
            str_contains($value, 'remote') => 'gate_remote',
            str_contains($value, 'pin'), str_contains($value, 'code') => 'pin_code',
            str_contains($value, 'master') => 'master_key',
            str_contains($value, 'lockbox') => 'lockbox',
            default => 'physical_key',
        };
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_incidents');
        Schema::dropIfExists('pm_premise_condition_records');
        Schema::dropIfExists('pm_premise_access_authorisations');
        Schema::dropIfExists('pm_premise_access_events');
        Schema::dropIfExists('pm_premise_access_items');
    }
};
