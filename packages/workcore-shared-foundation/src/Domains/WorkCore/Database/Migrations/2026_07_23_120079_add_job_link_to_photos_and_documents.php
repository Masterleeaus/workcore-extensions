<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pm_property_photos') && !Schema::hasColumn('pm_property_photos', 'property_job_id')) {
            Schema::table('pm_property_photos', function (Blueprint $table) {
                $table->unsignedBigInteger('property_job_id')->nullable()->index()->after('property_id');
                $table->index(['company_id', 'property_job_id']);
            });
        }

        if (Schema::hasTable('pm_property_documents') && !Schema::hasColumn('pm_property_documents', 'property_job_id')) {
            Schema::table('pm_property_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('property_job_id')->nullable()->index()->after('property_id');
                $table->index(['company_id', 'property_job_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_property_photos') && Schema::hasColumn('pm_property_photos', 'property_job_id')) {
            Schema::table('pm_property_photos', function (Blueprint $table) {
                $table->dropColumn('property_job_id');
            });
        }

        if (Schema::hasTable('pm_property_documents') && Schema::hasColumn('pm_property_documents', 'property_job_id')) {
            Schema::table('pm_property_documents', function (Blueprint $table) {
                $table->dropColumn('property_job_id');
            });
        }
    }
};
