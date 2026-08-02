<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_properties')) {
            return;
        }

        Schema::table('pm_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_properties', 'profile_key')) {
                $table->string('profile_key', 60)->default('service_site')->index()->after('type');
            }
            if (!Schema::hasColumn('pm_properties', 'terminology_overrides')) {
                $table->json('terminology_overrides')->nullable()->after('profile_key');
            }
            if (!Schema::hasColumn('pm_properties', 'feature_overrides')) {
                $table->json('feature_overrides')->nullable()->after('terminology_overrides');
            }
            if (!Schema::hasColumn('pm_properties', 'profile_attributes')) {
                $table->json('profile_attributes')->nullable()->after('feature_overrides');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pm_properties')) {
            return;
        }

        Schema::table('pm_properties', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('pm_properties', 'profile_key') ? 'profile_key' : null,
                Schema::hasColumn('pm_properties', 'terminology_overrides') ? 'terminology_overrides' : null,
                Schema::hasColumn('pm_properties', 'feature_overrides') ? 'feature_overrides' : null,
                Schema::hasColumn('pm_properties', 'profile_attributes') ? 'profile_attributes' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
