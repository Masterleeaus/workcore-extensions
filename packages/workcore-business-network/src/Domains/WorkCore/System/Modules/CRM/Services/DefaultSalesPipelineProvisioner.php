<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class DefaultSalesPipelineProvisioner
{
    public const STAGES = [
        ['key' => 'new', 'name' => 'New', 'color' => '#64748b', 'probability' => 10, 'position' => 0, 'is_closed' => false, 'is_won' => false],
        ['key' => 'qualified', 'name' => 'Qualified', 'color' => '#2563eb', 'probability' => 30, 'position' => 1, 'is_closed' => false, 'is_won' => false],
        ['key' => 'proposal', 'name' => 'Proposal', 'color' => '#9333ea', 'probability' => 55, 'position' => 2, 'is_closed' => false, 'is_won' => false],
        ['key' => 'negotiation', 'name' => 'Negotiation', 'color' => '#d97706', 'probability' => 80, 'position' => 3, 'is_closed' => false, 'is_won' => false],
        ['key' => 'won', 'name' => 'Won', 'color' => '#16a34a', 'probability' => 100, 'position' => 4, 'is_closed' => true, 'is_won' => true],
        ['key' => 'lost', 'name' => 'Lost', 'color' => '#dc2626', 'probability' => 0, 'position' => 5, 'is_closed' => true, 'is_won' => false],
    ];

    public function __construct(private ConnectionInterface $db) {}

    /** @return array<string,mixed> */
    public function ensure(int $companyId, ?int $actorId = null): array
    {
        return $this->db->transaction(function () use ($companyId, $actorId): array {
            $pipeline = $this->db->table('tz_sales_pipelines')
                ->where('company_id', $companyId)
                ->where('key', 'sales')
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($pipeline === null) {
                $pipelineId = $this->db->table('tz_sales_pipelines')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'key' => 'sales',
                    'name' => 'Sales Pipeline',
                    'is_default' => true,
                    'is_active' => true,
                    'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $pipeline = $this->db->table('tz_sales_pipelines')->where('id', $pipelineId)->first();
            }

            foreach (self::STAGES as $stage) {
                $exists = $this->db->table('tz_sales_pipeline_stages')
                    ->where('pipeline_id', $pipeline->id)
                    ->where('key', $stage['key'])
                    ->whereNull('deleted_at')
                    ->exists();
                if ($exists) {
                    continue;
                }
                $this->db->table('tz_sales_pipeline_stages')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'pipeline_id' => $pipeline->id,
                    'key' => $stage['key'],
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'probability' => $stage['probability'],
                    'position' => $stage['position'],
                    'is_closed' => $stage['is_closed'],
                    'is_won' => $stage['is_won'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (array) $this->db->table('tz_sales_pipelines')
                ->where('id', $pipeline->id)
                ->first(['public_id', 'key', 'name', 'is_default', 'is_active']);
        }, 3);
    }
}
