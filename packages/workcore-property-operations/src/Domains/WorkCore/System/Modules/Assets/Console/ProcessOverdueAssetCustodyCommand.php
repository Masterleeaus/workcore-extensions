<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class ProcessOverdueAssetCustodyCommand extends Command
{
    protected $signature = 'workcore:assets:process-overdue-custody {--company= : Restrict to one company ID} {--dry-run : Report without changing records}';
    protected $description = 'Mark overdue asset custody and publish canonical overdue events.';

    public function __construct(private ConnectionInterface $db)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = $this->db->table('tz_asset_assignments as assignments')
            ->join('tz_assets as assets', 'assets.id', '=', 'assignments.asset_id')
            ->where('assignments.active', true)
            ->where('assignments.active_lock', 'active')
            ->whereNotNull('assignments.expected_return_at')
            ->where('assignments.expected_return_at', '<', now())
            ->where('assignments.status', '!=', 'overdue')
            ->whereNull('assets.deleted_at')
            ->select([
                'assignments.id', 'assignments.public_id', 'assignments.company_id',
                'assignments.expected_return_at', 'assets.public_id as asset_public_id',
            ]);

        if ($companyId = (int) $this->option('company')) {
            $query->where('assignments.company_id', $companyId);
        }

        $processed = 0;
        foreach ($query->orderBy('assignments.company_id')->orderBy('assignments.id')->cursor() as $candidate) {
            if ((bool) $this->option('dry-run')) {
                $this->line("overdue company={$candidate->company_id} asset={$candidate->asset_public_id} custody={$candidate->public_id}");
                $processed++;
                continue;
            }

            $marked = $this->db->transaction(function () use ($candidate): bool {
                $assignment = $this->db->table('tz_asset_assignments')
                    ->where('id', $candidate->id)
                    ->where('company_id', $candidate->company_id)
                    ->lockForUpdate()
                    ->first();

                if (! $assignment || ! $assignment->active || $assignment->active_lock !== 'active'
                    || $assignment->expected_return_at === null || now()->lte($assignment->expected_return_at)
                    || $assignment->status === 'overdue') {
                    return false;
                }

                $occurredAt = now();
                $this->db->table('tz_asset_assignments')->where('id', $assignment->id)->update([
                    'status' => 'overdue',
                    'overdue_at' => $assignment->overdue_at ?? $occurredAt,
                    'updated_at' => $occurredAt,
                ]);

                $payload = [
                    'asset_public_id' => $candidate->asset_public_id,
                    'assignment_public_id' => $assignment->public_id,
                    'expected_return_at' => $assignment->expected_return_at,
                    'overdue_at' => $occurredAt->toISOString(),
                ];
                $this->publish((int) $candidate->company_id, (string) $candidate->asset_public_id, $payload, $occurredAt);

                return true;
            }, 3);

            if ($marked) {
                $processed++;
            }
        }

        $this->info("Processed {$processed} overdue custody records.");

        return self::SUCCESS;
    }

    /** @param array<string,mixed> $payload */
    private function publish(int $companyId, string $assetPublicId, array $payload, mixed $occurredAt): void
    {
        $eventId = (string) Str::ulid();
        $correlationId = 'asset-overdue:' . $eventId;
        $eventName = 'workcore.asset.custody.overdue';
        $envelope = [
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_version' => 1,
            'company_id' => $companyId,
            'actor_user_id' => null,
            'aggregate_type' => 'asset',
            'aggregate_public_id' => $assetPublicId,
            'correlation_id' => $correlationId,
            'causation_id' => null,
            'payload' => $payload,
            'occurred_at' => $occurredAt->toISOString(),
            'consumers' => (array) config('workcore.outbox.consumers', []),
        ];

        $this->db->table('tz_domain_events')->insert([
            'event_id' => $eventId,
            'event_name' => $eventName,
            'event_version' => 1,
            'company_id' => $companyId,
            'actor_user_id' => null,
            'aggregate_type' => 'asset',
            'aggregate_public_id' => $assetPublicId,
            'correlation_id' => $correlationId,
            'causation_id' => null,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        if ((bool) config('workcore.outbox.enabled', true)) {
            $this->db->table('tz_outbox_messages')->insert([
                'message_id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'topic' => 'workcore.domain_event',
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
                'causation_id' => null,
                'payload_json' => json_encode($envelope, JSON_THROW_ON_ERROR),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }
    }
}
