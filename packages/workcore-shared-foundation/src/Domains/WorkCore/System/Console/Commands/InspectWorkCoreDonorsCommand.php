<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Console\Commands;

use App\Domains\WorkCore\System\Donors\DonorSourceRegistry;
use Illuminate\Console\Command;

final class InspectWorkCoreDonorsCommand extends Command
{
    protected $signature = 'workcore:donors {--json : Emit machine-readable JSON}';
    protected $description = 'List extracted WorkCore donor sources and their integration status.';

    public function handle(DonorSourceRegistry $registry): int
    {
        $rows = array_map(
            static fn ($source): array => $source->toArray(),
            array_values($registry->all()),
        );

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(['sources' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Target', 'Files', 'Conflicts', 'Missing', 'Status'],
            array_map(static fn (array $row): array => [
                $row['key'], $row['target_module'], $row['file_count'], $row['conflict_count'],
                $row['missing_from_repaired_count'], $row['status'],
            ], $rows),
        );

        return self::SUCCESS;
    }
}
