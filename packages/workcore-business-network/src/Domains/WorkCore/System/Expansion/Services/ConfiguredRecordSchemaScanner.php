<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use App\Domains\WorkCore\System\Expansion\Contracts\RecordSchemaInspectorContract;

final class ConfiguredRecordSchemaScanner
{
    /** @param array<string,array<int,string>> $capabilityTables */
    public function __construct(
        private RecordSchemaInspectorContract $inspector,
        private array $capabilityTables,
    ) {}

    /** @return array<int,array{capability_key:string,table:string,columns:array<int,string>}> */
    public function scan(): array
    {
        $schemas = [];
        foreach ($this->capabilityTables as $capabilityKey => $tables) {
            $capabilityKey = trim((string) $capabilityKey);
            if ($capabilityKey === '') {
                continue;
            }

            foreach ($tables as $table) {
                $table = trim((string) $table);
                if ($table === '') {
                    continue;
                }
                $columns = array_values(array_unique(array_filter(
                    array_map(static fn (mixed $column): string => trim((string) $column), $this->inspector->columns($table)),
                    static fn (string $column): bool => $column !== '',
                )));
                if ($columns === []) {
                    continue;
                }
                $schemas[] = [
                    'capability_key' => $capabilityKey,
                    'table' => $table,
                    'columns' => $columns,
                ];
            }
        }

        return $schemas;
    }
}
