<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tz_asset_assignments', 'overdue_at')) {
            Schema::table('tz_asset_assignments', function (Blueprint $table): void {
                $table->timestamp('overdue_at')->nullable()->after('expected_return_at');
            });
        }

        Schema::create('tz_asset_legacy_status_migrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->timestamps();
            $table->unique('asset_id', 'tz_asset_legacy_status_asset_unique');
            $table->index(['company_id', 'from_status'], 'tz_asset_legacy_status_company_idx');
        });

        DB::table('tz_assets')
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(250, function ($assets): void {
                foreach ($assets as $asset) {
                    $now = now();
                    DB::table('tz_asset_legacy_status_migrations')->insert([
                        'company_id' => $asset->company_id,
                        'asset_id' => $asset->id,
                        'from_status' => 'active',
                        'to_status' => 'available',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('tz_assets')->where('id', $asset->id)->where('status', 'active')->update([
                        'status' => 'available',
                        'lock_version' => (int) ($asset->lock_version ?? 0) + 1,
                        'updated_at' => $now,
                    ]);
                    DB::table('tz_asset_status_history')->insert([
                        'public_id' => (string) Str::ulid(),
                        'company_id' => $asset->company_id,
                        'asset_id' => $asset->id,
                        'from_status' => 'active',
                        'to_status' => 'available',
                        'from_condition' => $asset->condition,
                        'to_condition' => $asset->condition,
                        'reason' => 'Legacy WorkCore asset status normalised.',
                        'override_used' => false,
                        'changed_by_user_id' => null,
                        'approved_by_user_id' => null,
                        'correlation_id' => 'asset-status-normalisation:' . $asset->id,
                        'context' => json_encode(['migration' => '2026_07_19_000027'], JSON_THROW_ON_ERROR),
                        'changed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('tz_asset_legacy_status_migrations')) {
            DB::table('tz_asset_legacy_status_migrations')
                ->chunkById(250, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('tz_assets')
                            ->where('id', $row->asset_id)
                            ->where('status', $row->to_status)
                            ->update([
                                'status' => $row->from_status,
                                'updated_at' => now(),
                            ]);
                        DB::table('tz_asset_status_history')
                            ->where('asset_id', $row->asset_id)
                            ->where('correlation_id', 'asset-status-normalisation:' . $row->asset_id)
                            ->delete();
                    }
                }, 'id');

            Schema::dropIfExists('tz_asset_legacy_status_migrations');
        }

        if (Schema::hasColumn('tz_asset_assignments', 'overdue_at')) {
            Schema::table('tz_asset_assignments', function (Blueprint $table): void {
                $table->dropColumn('overdue_at');
            });
        }
    }
};
