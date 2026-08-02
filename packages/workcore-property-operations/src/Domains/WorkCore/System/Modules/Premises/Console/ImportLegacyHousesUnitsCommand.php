<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class ImportLegacyHousesUnitsCommand extends Command
{
    protected $signature = 'pm:import-legacy {--dry-run : Do not write to database}';

    protected $description = 'Import legacy houses and units tables into Managed Premises.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $imported = 0;

        if (Schema::hasTable('houses')) {
            $this->info('Found legacy table: houses');
            $rows = DB::table('houses')->get();
            foreach ($rows as $row) {
                $payload = [
                    'company_id' => $row->company_id,
                    'property_code' => $row->house_code ?? null,
                    'name' => $row->house_name ?? ($row->house_code ?? null),
                    'type' => 'house',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($dry) {
                    $this->line('DRY: would import house ' . ($row->id ?? '?') . ' => ' . ($payload['name'] ?? '')); 
                    $imported++;
                    continue;
                }

                Property::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $row->company_id, 'property_code' => $row->house_code],
                    $payload
                );
                $imported++;
            }
        }

        if (Schema::hasTable('units')) {
            $this->info('Found legacy table: units');
            $rows = DB::table('units')->get();
            foreach ($rows as $row) {
                $payload = [
                    'company_id' => $row->company_id,
                    'property_code' => $row->unit_code ?? null,
                    'name' => $row->unit_name ?? ($row->unit_code ?? null),
                    'type' => 'unit',
                    'status' => 'active',
                    'address_line1' => $row->address ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($dry) {
                    $this->line('DRY: would import unit ' . ($row->id ?? '?') . ' => ' . ($payload['name'] ?? ''));
                    $imported++;
                    continue;
                }

                Property::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $row->company_id, 'property_code' => $row->unit_code],
                    $payload
                );
                $imported++;
            }
        }

        $this->info('Imported records: ' . $imported . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
