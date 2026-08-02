<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pm_properties')) {
            return;
        }
        Schema::create('pm_properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->string('property_code')->nullable()->index();
            $table->string('name')->nullable();

            // house | building | unit (top-level property type)
            $table->string('type', 30)->default('house')->index();
            $table->string('status', 30)->default('active')->index();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('suburb')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Tradie-friendly context
            $table->longText('access_notes')->nullable();
            $table->longText('hazards')->nullable();
            $table->string('parking_notes')->nullable();
            $table->string('lockbox_code')->nullable();
            $table->string('keys_location')->nullable();

            // Service window preference (useful for cleaners/agents)
            $table->dateTime('preferred_window_start')->nullable();
            $table->dateTime('preferred_window_end')->nullable();

            // Owner/Agent metadata (lightweight; richer contacts live in contacts table)
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_phone')->nullable();
            $table->string('primary_contact_email')->nullable();

            // Optional linkage to an existing customer/client record (if present in this Worksuite build)
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_properties');
    }
};
