<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Entitlements;

use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use JsonException;

final class WorkCorePlanEntitlementAdapter
{
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
        $projectedCapabilities = [];
        $sourceFeatures = [];

        foreach ($this->featureMap as $featureKey => $capabilityKeys) {
            if (! is_string($featureKey) || ! is_array($capabilityKeys)) {
                continue;
            }
            foreach ($capabilityKeys as $capabilityKey) {
                if (! is_string($capabilityKey) || ! $this->capabilities->has($capabilityKey)) {
                    continue;
                }
                $projectedCapabilities[$capabilityKey] ??= false;
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

        $sourcePayload = [
            'subscription_id' => $subscription->subscriptionId,
            'plan_id' => $subscription->planId,
            'status' => strtolower(trim($subscription->status)),
            'features' => $subscription->features,
            'source_revision' => $subscription->sourceRevision,
            'valid_from' => $subscription->accessValidFrom?->format(DATE_ATOM),
            'valid_until' => $subscription->accessValidUntil?->format(DATE_ATOM),
            'projected_capabilities' => $projectedCapabilities,
        ];
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
            $stateQuery = $this->db->table('tz_company_entitlement_states')
                ->where('company_id', $companyId)
                ->lockForUpdate();
            $current = $stateQuery->first();

            if ($current !== null && hash_equals((string) $current->source_checksum, $sourceChecksum)) {
                return (int) $current->revision;
            }

            $revision = $current === null ? 1 : ((int) $current->revision + 1);
            $timestamp = $now->format('Y-m-d H:i:s');
            $state = [
                'company_id' => $companyId,
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
            ];
            if ($current === null) {
                $state['created_at'] = $timestamp;
            }
            $this->db->table('tz_company_entitlement_states')->updateOrInsert(
                ['company_id' => $companyId],
                $state,
            );

            $this->db->table('tz_company_entitlement_projections')
                ->where('company_id', $companyId)
                ->delete();

            foreach ($projectedCapabilities as $capabilityKey => $enabled) {
                $this->db->table('tz_company_entitlement_projections')->updateOrInsert(
                    ['company_id' => $companyId, 'capability_key' => $capabilityKey],
                    [
                        'revision' => $revision,
                        'enabled' => $enabled,
                        'source_feature_key' => implode(',', $sourceFeatures[$capabilityKey] ?? []),
                        'valid_until' => $subscription->accessValidUntil?->format('Y-m-d H:i:s'),
                        'metadata' => json_encode([
                            'subscription_id' => $subscription->subscriptionId,
                            'plan_id' => $subscription->planId,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                );
            }

            return $revision;
        }, 3);
    }
}
