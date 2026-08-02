<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_property_photos')) {
            return;
        }

        Schema::table('pm_property_photos', function (Blueprint $table) {
            // Add explicit uploaded_at timestamp (distinct from updated_at)
            if (!Schema::hasColumn('pm_property_photos', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('caption');
            }
            // Add file_path as a standard alias for 'path' (interoperability)
            if (!Schema::hasColumn('pm_property_photos', 'file_path')) {
                $table->string('file_path')->nullable()->after('path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pm_property_photos')) {
            return;
        }

        Schema::table('pm_property_photos', function (Blueprint $table) {
            if (Schema::hasColumn('pm_property_photos', 'uploaded_at')) {
                $table->dropColumn('uploaded_at');
            }
            if (Schema::hasColumn('pm_property_photos', 'file_path')) {
                $table->dropColumn('file_path');
            }
        });
    }
};
