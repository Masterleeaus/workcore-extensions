<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $features = [
        'ext_workcore_core',
        'ext_workcore_operations',
        'ext_workcore_workforce',
        'ext_workcore_resources',
        'ext_workcore_commercial',
        'ext_workcore_offline',
        'ext_workcore_ai_actions',
    ];

    public function up(): void
    {
        $plansTable = $this->plansTable();
        if (! Schema::hasTable($plansTable)) {
            return;
        }

        $missing = array_values(array_filter(
            $this->features,
            static fn (string $feature): bool => ! Schema::hasColumn($plansTable, $feature),
        ));
        if ($missing === []) {
            return;
        }

        Schema::table($plansTable, static function (Blueprint $table) use ($missing): void {
            foreach ($missing as $feature) {
                $table->boolean($feature)->default(false);
            }
        });
    }

    public function down(): void
    {
        $plansTable = $this->plansTable();
        if (! Schema::hasTable($plansTable)) {
            return;
        }

        $existing = array_values(array_filter(
            $this->features,
            static fn (string $feature): bool => Schema::hasColumn($plansTable, $feature),
        ));
        if ($existing === []) {
            return;
        }

        Schema::table($plansTable, static function (Blueprint $table) use ($existing): void {
            $table->dropColumn($existing);
        });
    }

    private function plansTable(): string
    {
        $plansTable = trim((string) config(
            'workcore-native.entitlements.subscription_source.plans_table',
            'plans',
        ));
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $plansTable)) {
            throw new \RuntimeException("Unsafe MagicAI plans table identifier [{$plansTable}].");
        }

        return $plansTable;
    }
};
