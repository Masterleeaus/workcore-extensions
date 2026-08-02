<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pm_property_jobs')) {
            return;
        }
        Schema::create('pm_property_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();

            // Optional linkage to an existing job/work order/invoice/etc.
            $table->string('linked_module')->nullable(); // jobs | workorders | invoices | custom
            $table->unsignedBigInteger('linked_id')->nullable()->index();

            $table->string('title');
            $table->string('status', 30)->default('open')->index();
            $table->dateTime('scheduled_at')->nullable();
            $table->longText('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('pm_properties')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_jobs');
    }
};
