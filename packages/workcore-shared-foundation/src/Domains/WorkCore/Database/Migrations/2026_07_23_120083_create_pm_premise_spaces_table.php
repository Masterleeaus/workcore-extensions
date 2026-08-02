<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_spaces')) {
            Schema::create('pm_premise_spaces', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('parent_space_id')->nullable()->index();
                $table->string('space_type', 80)->default('space')->index();
                $table->string('code', 100)->nullable()->index();
                $table->string('name', 191);
                $table->string('status', 40)->default('active')->index();
                $table->string('availability_status', 40)->default('available')->index();
                $table->string('floor', 100)->nullable();
                $table->string('zone', 100)->nullable();
                $table->unsignedInteger('capacity')->nullable();
                $table->decimal('area', 12, 2)->nullable();
                $table->string('address_override')->nullable();
                $table->boolean('is_occupiable')->default(false);
                $table->boolean('is_bookable')->default(false);
                $table->boolean('is_billable')->default(false);
                $table->boolean('is_shared')->default(false);
                $table->string('legacy_source_type', 40)->nullable()->index();
                $table->unsignedBigInteger('legacy_source_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('parent_space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->unique(['company_id', 'legacy_source_type', 'legacy_source_id'], 'pm_spaces_legacy_unique');
                $table->index(['company_id', 'premise_id', 'parent_space_id'], 'pm_spaces_tree_index');
            });
        }

        $now = now();
        if (Schema::hasTable('pm_property_units')) {
            DB::table('pm_property_units')->orderBy('id')->chunkById(200, function ($units) use ($now) {
                foreach ($units as $unit) {
                    DB::table('pm_premise_spaces')->updateOrInsert(
                        ['company_id' => $unit->company_id, 'legacy_source_type' => 'unit', 'legacy_source_id' => $unit->id],
                        [
                            'user_id' => $unit->user_id ?? null,
                            'premise_id' => $unit->property_id,
                            'space_type' => $unit->type ?: 'unit',
                            'code' => $unit->unit_code,
                            'name' => $unit->unit_name ?: ('Unit '.$unit->id),
                            'status' => 'active',
                            'availability_status' => 'available',
                            'floor' => $unit->floor,
                            'zone' => $unit->tower,
                            'area' => $unit->area,
                            'address_override' => $unit->address,
                            'is_occupiable' => true,
                            'is_bookable' => false,
                            'is_billable' => true,
                            'is_shared' => false,
                            'metadata' => json_encode(['migrated_from' => 'pm_property_units']),
                            'created_at' => $unit->created_at ?? $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
        }

        if (Schema::hasTable('pm_property_rooms')) {
            DB::table('pm_property_rooms')->orderBy('id')->chunkById(200, function ($rooms) use ($now) {
                foreach ($rooms as $room) {
                    DB::table('pm_premise_spaces')->updateOrInsert(
                        ['company_id' => $room->company_id, 'legacy_source_type' => 'room', 'legacy_source_id' => $room->id],
                        [
                            'user_id' => $room->user_id ?? null,
                            'premise_id' => $room->property_id,
                            'space_type' => $room->type ?: 'room',
                            'name' => $room->name,
                            'status' => 'active',
                            'availability_status' => 'available',
                            'is_occupiable' => true,
                            'is_bookable' => false,
                            'is_billable' => false,
                            'is_shared' => false,
                            'metadata' => json_encode(['notes' => $room->notes, 'migrated_from' => 'pm_property_rooms']),
                            'created_at' => $room->created_at ?? $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_spaces');
    }
};
