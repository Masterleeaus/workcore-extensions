<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

final class ListOverdueAssetCustodyCommand extends Command
{
    protected $signature = 'workcore:assets:overdue-custody {--company= : Restrict to one company ID}';
    protected $description = 'List active asset custody records past their expected return.';

    public function __construct(private ConnectionInterface $db)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = $this->db->table('tz_asset_assignments as assignments')
            ->join('tz_assets as assets', 'assets.id', '=', 'assignments.asset_id')
            ->where('assignments.active', true)
            ->whereNotNull('assignments.expected_return_at')
            ->where('assignments.expected_return_at', '<', now())
            ->select(['assignments.company_id', 'assets.public_id', 'assets.name', 'assignments.assignment_type', 'assignments.expected_return_at']);
        if ($company = (int) $this->option('company')) {
            $query->where('assignments.company_id', $company);
        }
        $rows = $query->orderBy('assignments.expected_return_at')->get()->map(fn (object $row): array => (array) $row)->all();
        $this->table(['Company', 'Asset', 'Name', 'Custody', 'Expected return'], $rows);
        return self::SUCCESS;
    }
}
