<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Entitlements;

use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class WorkCorePlanEntitlementAdapter
{
    private const STATE_TABLE = 'tz_company_entitlement_states';
    private const PROJECTION_TABLE = 'tz_company_entitlement_projections';

    /** @param array<string,list<string>> $featureMap */
    public function __construct(
        private ConnectionInterface $db,
        private CapabilityRegistry $capabilities,
        private array $featureMap,
    ) {}

    /** @throws JsonException */
    public function project(int $companyId, EffectiveSubscriptionSnapshot $subscription): int
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('A positive WorkCore company ID is required.');
        }

        $now = new DateTimeImmutable('now');
        $subscriptionActive = $subscription->grantsAccessAt($now);
        $projectedCapabilities = array_fill_keys(array_keys($this->capabilities->all()), false);
        $sourceFeatures = [];

        foreach ($this->featureMap as $featureKey => $capabilityKeys) {
            if (! is_string($featureKey) || trim($featureKey) === '' || ! is_array($capabilityKeys)) {
                continue;
            }

            foreach ($capabilityKeys as $capabilityKey) {
                if (! is_string($capabilityKey) || ! $this->capabilities->has($capabilityKey)) {
                    continue;
                }

                $sourceFeatures[$capabilityKey] ??= [];
                $sourceFeatures[$capabilityKey][] = $featureKey;
                if ($subscriptionActive && $subscription->featureEnabled($featureKey)) {
                    $projectedCapabilities[$capabilityKey] = true;
                }
            }
        }

        ksort($projectedCapabilities);
        foreach ($sourceFeatures as &$features) {
            $features = array_values(array_unique($features));
            sort($features);
        }
        unset($features);
        ksort($sourceFeatures);

        $sourcePayload = self::canonicalize([
            'subscription_id' => $subscription->subscriptionId,
            'plan_id' => $subscription->planId,
            'status' => strtolower(trim($subscription->status)),
            'features' => $subscription->features,
            'source_revision' => $subscription->sourceRevision,
            'valid_from' => $subscription->accessValidFrom?->format(DATE_ATOM),
            'valid_until' => $subscription->accessValidUntil?->format(DATE_ATOM),
            'metadata' => $subscription->metadata,
            'projected_capabilities' => $projectedCapabilities,
        ]);
        $sourceChecksum = hash('sha256', json_encode(
            $sourcePayload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));

        return $this->db->transaction(function () use (
            $companyId,
            $subscription,
            $projectedCapabilities,
            $sourceFeatures,
            $sourceChecksum,
            $now,
        ): int {
            $timestamp = $now->format('Y-m-d H:i:s');

            // Create a deterministic row before acquiring the lock. This makes the
            // first projection serialize just like every subsequent revision.
            $this->db->table(self::STATE_TABLE)->insertOrIgnore([
                'company_id' => $companyId,
                'revision' => 0,
                'source' => 'unprojected',
                'source_subscription_id' => null,
                'source_plan_id' => null,
                'source_revision' => null,
                'source_checksum' => hash('sha256', 'workcore:unprojected'),
                'status' => 'unprojected',
                'valid_from' => null,
                'valid_until' => null,
                'projected_at' => $timestamp,
                'metadata' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $current = $this->db->table(self::STATE_TABLE)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();
            if ($current === null) {
                throw new RuntimeException('Unable to lock the WorkCore entitlement state.');
            }

            if ((int) $current->revision > 0
                && hash_equals((string) $current->source_checksum, $sourceChecksum)) {
                return (int) $current->revision;
            }

            $revision = (int) $current->revision + 1;
            $this->db->table(self::STATE_TABLE)
                ->where('company_id', $companyId)
                ->update([
                    'revision' => $revision,
                    'source' => 'magicai_subscription',
                    'source_subscription_id' => $subscription->subscriptionId,
                    'source_plan_id' => $subscription->planId,
                    'source_revision' => $subscription->sourceRevision,
                    'source_checksum' => $sourceChecksum,
                    'status' => strtolower(trim($subscription->status)),
                    'valid_from' => $subscription->accessValidFrom?->format('Y-m-d H:i:s'),
                    'valid_until' => $subscription->accessValidUntil?->format('Y-m-d H:i:s'),
                    'projected_at' => $timestamp,
                    'metadata' => json_encode($subscription->metadata, JSON_THROW_ON_ERROR),
                    'updated_at' => $timestamp,
                ]);

            $this->db->table(self::PROJECTION_TABLE)
                ->where('company_id', $companyId)
                ->delete();

            foreach ($projectedCapabilities as $capabilityKey => $enabled) {
                $this->db->table(self::PROJECTION_TABLE)->updateOrInsert(
                    ['company_id' => $companyId, 'capability_key' => $capabilityKey],
                    [
                        'revision' => $revision,
                        'enabled' => $enabled,
                        'source_feature_key' => implode(',', $sourceFeatures[$capabilityKey] ?? []),
                        'valid_until' => $subscription->accessValidUntil?->format('Y-m-d H:i:s'),
                        'metadata' => json_encode([
                            'mapped' => isset($sourceFeatures[$capabilityKey]),
                            'source_feature_keys' => $sourceFeatures[$capabilityKey] ?? [],
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            }

            return $revision;
        }, 3);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
