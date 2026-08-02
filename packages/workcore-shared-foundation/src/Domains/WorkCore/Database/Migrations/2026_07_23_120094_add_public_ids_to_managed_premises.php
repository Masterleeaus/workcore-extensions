<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $this->addPublicId('pm_properties', 'pm_properties_company_public_unique');
        $this->addPublicId('pm_premise_spaces', 'pm_spaces_company_public_unique');
        $this->addPublicId('pm_premise_party_roles', 'pm_parties_company_public_unique');
        $this->addPublicId('pm_premise_occupancies', 'pm_occupancies_company_public_unique');
        $this->addPublicId('pm_premise_agreements', 'pm_agreements_company_public_unique');
    }

    private function addPublicId(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }
        if (! Schema::hasColumn($tableName, 'public_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->char('public_id', 26)->nullable()->after('id');
            });
        }

        DB::table($tableName)
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(250, function ($records) use ($tableName): void {
                foreach ($records as $record) {
                    DB::table($tableName)->where('id', $record->id)->update(['public_id' => (string) Str::ulid()]);
                }
            });

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->unique(['company_id', 'public_id'], $indexName);
        });
    }

    public function down(): void
    {
        // Public IDs are retained because external references may already depend on them.
    }
};
