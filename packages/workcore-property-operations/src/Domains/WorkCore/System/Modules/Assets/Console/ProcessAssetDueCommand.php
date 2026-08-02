<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class ProcessAssetDueCommand extends Command
{
    protected $signature = 'workcore:assets:process-due {--company= : Restrict to one company ID} {--dry-run : Report without changing records}';
    protected $description = 'Create due maintenance work and publish maintenance/calibration due events.';

    public function __construct(private ConnectionInterface $db)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = $this->db->table('tz_asset_maintenance_plans as plans')
            ->join('tz_assets as assets', 'assets.id', '=', 'plans.asset_id')
            ->where('plans.active', true)
            ->whereNotNull('plans.next_due_at')
            ->where('plans.next_due_at', '<=', now())
            ->whereNull('assets.deleted_at')
            ->select(['plans.*', 'assets.public_id as asset_public_id', 'assets.status as asset_status', 'assets.condition as asset_condition']);
        if ($company = (int) $this->option('company')) {
            $query->where('plans.company_id', $company);
        }

        $processed = 0;
        foreach ($query->orderBy('plans.company_id')->orderBy('plans.id')->cursor() as $plan) {
            if ((bool) $this->option('dry-run')) {
                $this->line("due company={$plan->company_id} asset={$plan->asset_public_id} plan={$plan->public_id}");
                $processed++;
                continue;
            }

            $created = $this->db->transaction(function () use ($plan): bool {
                $locked = $this->db->table('tz_asset_maintenance_plans')->where('id', $plan->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->active || $locked->next_due_at === null || now()->lt($locked->next_due_at)) {
                    return false;
                }
                if ($this->db->table('tz_asset_maintenance_records')
                    ->where('company_id', $plan->company_id)
                    ->where('asset_id', $plan->asset_id)
                    ->where('maintenance_plan_id', $plan->id)
                    ->whereIn('status', ['due', 'scheduled', 'in_progress', 'awaiting_parts'])
                    ->exists()) {
                    return false;
                }

                $now = now();
                $maintenancePublicId = (string) Str::ulid();
                $this->db->table('tz_asset_maintenance_records')->insert([
                    'public_id' => $maintenancePublicId,
                    'company_id' => $plan->company_id,
                    'asset_id' => $plan->asset_id,
                    'maintenance_plan_id' => $plan->id,
                    'maintenance_type' => $plan->calibration_required ? 'calibration' : 'preventive',
                    'status' => 'due',
                    'priority' => 'normal',
                    'due_at' => $plan->next_due_at,
                    'notes' => 'Created by WorkCore asset due processor.',
                    'created_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $asset = $this->db->table('tz_assets')->where('id', $plan->asset_id)->lockForUpdate()->first();
                if ($asset && in_array((string) $asset->status, ['available', 'assigned', 'checked_out', 'in_use'], true)) {
                    $this->db->table('tz_assets')->where('id', $asset->id)->update([
                        'status' => 'maintenance_due',
                        'lock_version' => (int) ($asset->lock_version ?? 0) + 1,
                        'updated_at' => $now,
                    ]);
                    $this->db->table('tz_asset_status_history')->insert([
                        'public_id' => (string) Str::ulid(),
                        'company_id' => $plan->company_id,
                        'asset_id' => $asset->id,
                        'from_status' => $asset->status,
                        'to_status' => 'maintenance_due',
                        'from_condition' => $asset->condition,
                        'to_condition' => $asset->condition,
                        'reason' => 'Maintenance plan became due.',
                        'override_used' => false,
                        'changed_by_user_id' => null,
                        'approved_by_user_id' => null,
                        'correlation_id' => 'asset-due:' . $maintenancePublicId,
                        'context' => json_encode(['maintenance_plan_public_id' => $plan->public_id], JSON_THROW_ON_ERROR),
                        'changed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $payload = [
                    'asset_public_id' => $plan->asset_public_id,
                    'maintenance_plan_public_id' => $plan->public_id,
                    'maintenance_public_id' => $maintenancePublicId,
                    'due_at' => $plan->next_due_at,
                ];
                $this->publish($plan->company_id, 'workcore.asset.maintenance_due', $plan->asset_public_id, $payload);
                if ($plan->calibration_required) {
                    $this->db->table('tz_assets')->where('id', $plan->asset_id)->update([
                        'next_calibration_due_on' => $plan->next_due_at,
                        'updated_at' => $now,
                    ]);
                    $this->publish($plan->company_id, 'workcore.asset.calibration_due', $plan->asset_public_id, $payload);
                }
                return true;
            }, 3);
            if ($created) {
                $processed++;
            }
        }

        $processed += $this->processWarrantyExpiries((int) $this->option('company'));

        $this->info("Processed {$processed} due asset items.");
        return self::SUCCESS;
    }

    private function processWarrantyExpiries(int $companyId = 0): int
    {
        $warningDays = max(0, (int) config('workcore.assets.warranty_expiry_warning_days', 30));
        $today = now()->startOfDay();
        $warningDate = $today->copy()->addDays($warningDays)->toDateString();
        $query = $this->db->table('tz_asset_warranties as warranties')
            ->join('tz_assets as assets', function ($join): void {
                $join->on('assets.id', '=', 'warranties.asset_id')
                    ->on('assets.company_id', '=', 'warranties.company_id');
            })
            ->where('warranties.status', 'active')
            ->whereNull('warranties.deleted_at')
            ->whereNull('assets.deleted_at')
            ->whereDate('warranties.expires_on', '<=', $warningDate)
            ->select([
                'warranties.id',
                'warranties.public_id',
                'warranties.company_id',
                'warranties.expires_on',
                'warranties.expiry_alerted_at',
                'warranties.expired_event_at',
                'assets.public_id as asset_public_id',
            ]);
        if ($companyId > 0) {
            $query->where('warranties.company_id', $companyId);
        }

        $processed = 0;
        foreach ($query->orderBy('warranties.company_id')->orderBy('warranties.id')->cursor() as $warranty) {
            $expired = (string) $warranty->expires_on < $today->toDateString();
            if ((bool) $this->option('dry-run')) {
                $state = $expired ? 'expired' : 'expiring';
                $this->line("warranty {$state} company={$warranty->company_id} asset={$warranty->asset_public_id} warranty={$warranty->public_id}");
                $processed++;
                continue;
            }

            $published = $this->db->transaction(function () use ($warranty, $expired): bool {
                $locked = $this->db->table('tz_asset_warranties')
                    ->where('company_id', $warranty->company_id)
                    ->where('id', $warranty->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();
                if (! $locked || (string) $locked->status !== 'active') {
                    return false;
                }
                if ($expired) {
                    if ($locked->expired_event_at !== null) {
                        return false;
                    }
                    $this->db->table('tz_asset_warranties')->where('id', $locked->id)->update([
                        'status' => 'expired',
                        'expired_event_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->publish((int) $locked->company_id, 'workcore.asset.warranty_expired', (string) $warranty->asset_public_id, [
                        'asset_public_id' => $warranty->asset_public_id,
                        'warranty_public_id' => $locked->public_id,
                        'expires_on' => $locked->expires_on,
                    ]);
                    return true;
                }
                if ($locked->expiry_alerted_at !== null) {
                    return false;
                }
                $this->db->table('tz_asset_warranties')->where('id', $locked->id)->update([
                    'expiry_alerted_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->publish((int) $locked->company_id, 'workcore.asset.warranty_expiring', (string) $warranty->asset_public_id, [
                    'asset_public_id' => $warranty->asset_public_id,
                    'warranty_public_id' => $locked->public_id,
                    'expires_on' => $locked->expires_on,
                ]);
                return true;
            }, 3);
            if ($published) {
                $processed++;
            }
        }

        return $processed;
    }

    /** @param array<string,mixed> $payload */
    private function publish(int $companyId, string $eventName, string $assetPublicId, array $payload): void
    {
        $eventId = (string) Str::ulid();
        $correlationId = 'asset-due:' . $eventId;
        $occurredAt = now();
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
            'event_id' => $eventId, 'event_name' => $eventName, 'event_version' => 1,
            'company_id' => $companyId, 'actor_user_id' => null,
            'aggregate_type' => 'asset', 'aggregate_public_id' => $assetPublicId,
            'correlation_id' => $correlationId, 'causation_id' => null,
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => $occurredAt, 'created_at' => $occurredAt, 'updated_at' => $occurredAt,
        ]);
        if ((bool) config('workcore.outbox.enabled', true)) {
            $this->db->table('tz_outbox_messages')->insert([
                'message_id' => (string) Str::ulid(), 'company_id' => $companyId,
                'topic' => 'workcore.domain_event', 'event_id' => $eventId,
                'correlation_id' => $correlationId, 'causation_id' => null,
                'payload_json' => json_encode($envelope, JSON_THROW_ON_ERROR),
                'status' => 'pending', 'attempts' => 0, 'available_at' => $occurredAt,
                'created_at' => $occurredAt, 'updated_at' => $occurredAt,
            ]);
        }
    }
}
