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
            // Property classification
            if (!Schema::hasColumn('pm_properties', 'property_type')) {
                // House, Apartment, Office, Warehouse, Strata Complex, Airbnb
                $table->string('property_type', 50)->nullable()->after('type');
            }

            // Room counts for quote/duration calculation
            if (!Schema::hasColumn('pm_properties', 'bedrooms')) {
                $table->unsignedTinyInteger('bedrooms')->nullable()->after('property_type');
            }
            if (!Schema::hasColumn('pm_properties', 'bathrooms')) {
                $table->unsignedTinyInteger('bathrooms')->nullable()->after('bedrooms');
            }

            // Property size for duration/quote calculation (m²)
            if (!Schema::hasColumn('pm_properties', 'property_size_sqm')) {
                $table->decimal('property_size_sqm', 8, 2)->nullable()->after('bathrooms');
            }

            // Cleaning frequency preferred at property level
            if (!Schema::hasColumn('pm_properties', 'cleaning_frequency')) {
                // weekly, fortnightly, monthly, one-off, custom
                $table->string('cleaning_frequency', 30)->nullable()->after('property_size_sqm');
            }

            // Key holding status (office holds key vs customer provides)
            if (!Schema::hasColumn('pm_properties', 'key_holding_status')) {
                // office_holds | customer_provides | lockbox | other
                $table->string('key_holding_status', 40)->nullable()->after('cleaning_frequency');
            }

            // Pet information and allergies for cleaners
            if (!Schema::hasColumn('pm_properties', 'pet_info')) {
                $table->text('pet_info')->nullable()->after('key_holding_status');
            }

            // Building/strata access requirements
            if (!Schema::hasColumn('pm_properties', 'strata_access_notes')) {
                $table->text('strata_access_notes')->nullable()->after('pet_info');
            }

            // Flag properties needing special equipment
            if (!Schema::hasColumn('pm_properties', 'special_equipment_needed')) {
                $table->boolean('special_equipment_needed')->default(false)->after('strata_access_notes');
            }
            if (!Schema::hasColumn('pm_properties', 'special_equipment_notes')) {
                $table->text('special_equipment_notes')->nullable()->after('special_equipment_needed');
            }

            // Alarm and gate codes (secure access)
            if (!Schema::hasColumn('pm_properties', 'alarm_code')) {
                $table->string('alarm_code', 120)->nullable();
            }
            if (!Schema::hasColumn('pm_properties', 'gate_code')) {
                $table->string('gate_code', 120)->nullable();
            }
            if (!Schema::hasColumn('pm_properties', 'intercom_code')) {
                $table->string('intercom_code', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pm_properties')) {
            return;
        }

        Schema::table('pm_properties', function (Blueprint $table) {
            $columns = [
                'property_type',
                'bedrooms',
                'bathrooms',
                'property_size_sqm',
                'cleaning_frequency',
                'key_holding_status',
                'pet_info',
                'strata_access_notes',
                'special_equipment_needed',
                'special_equipment_notes',
                'alarm_code',
                'gate_code',
                'intercom_code',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('pm_properties', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
