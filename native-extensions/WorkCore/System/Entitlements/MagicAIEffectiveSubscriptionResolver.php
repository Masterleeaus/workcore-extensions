<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Entitlements;

use App\Domains\WorkCore\System\Entitlements\Contracts\EffectiveSubscriptionResolverContract;
use App\Domains\WorkCore\System\Entitlements\EffectiveSubscriptionSnapshot;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Throwable;

final class MagicAIEffectiveSubscriptionResolver implements EffectiveSubscriptionResolverContract
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private ConnectionInterface $db,
        private array $config,
    ) {}

    public function resolve(int $companyId): EffectiveSubscriptionSnapshot
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('A positive WorkCore company ID is required.');
        }

        $companyTable = $this->identifier((string) ($this->config['companies_table'] ?? 'tz_companies'));
        $companyUserColumn = $this->identifier((string) ($this->config['company_user_column'] ?? 'owner_user_id'));
        $ownerUserId = $this->db->table($companyTable)
            ->where('id', $companyId)
            ->where('status', 'active')
            ->value($companyUserColumn);
        if (! is_numeric($ownerUserId) || (int) $ownerUserId < 1) {
            return $this->noSubscription($companyId);
        }

        $subscriptionsTable = $this->identifier((string) ($this->config['subscriptions_table'] ?? 'subscriptions'));
        $plansTable = $this->identifier((string) ($this->config['plans_table'] ?? 'plans'));
        $subscriptionIdColumn = $this->identifier((string) ($this->config['subscription_id_column'] ?? 'id'));
        $subscriptionUserColumn = $this->identifier((string) ($this->config['subscription_user_column'] ?? 'user_id'));
        $subscriptionPlanColumn = $this->identifier((string) ($this->config['subscription_plan_column'] ?? 'plan_id'));
        $statusColumn = $this->identifier((string) ($this->config['status_column'] ?? 'status'));
        $validFromColumn = $this->identifier((string) ($this->config['valid_from_column'] ?? 'starts_at'));
        $validUntilColumn = $this->identifier((string) ($this->config['valid_until_column'] ?? 'ends_at'));
        $updatedAtColumn = $this->identifier((string) ($this->config['updated_at_column'] ?? 'updated_at'));
        $planIdColumn = $this->identifier((string) ($this->config['plan_id_column'] ?? 'id'));
        $planFrequencyColumn = $this->identifier((string) ($this->config['plan_frequency_column'] ?? 'frequency'));
        $planUpdatedAtColumn = $this->identifier((string) ($this->config['plan_updated_at_column'] ?? 'updated_at'));
        $activeStatuses = array_values(array_filter(
            (array) ($this->config['active_statuses'] ?? []),
            static fn ($value): bool => is_string($value) && trim($value) !== '',
        ));
        if ($activeStatuses === []) {
            return $this->noSubscription($companyId);
        }

        $subscriptions = $this->db->table($subscriptionsTable)
            ->where($subscriptionUserColumn, (int) $ownerUserId)
            ->whereIn($statusColumn, $activeStatuses)
            ->orderByDesc($validUntilColumn)
            ->orderByDesc($updatedAtColumn)
            ->orderByDesc($subscriptionIdColumn)
            ->limit(50)
            ->get();

        $best = null;
        $bestRank = PHP_INT_MIN;
        $at = new DateTimeImmutable('now');
        foreach ($subscriptions as $subscription) {
            $planId = $this->property($subscription, $subscriptionPlanColumn);
            if (! is_numeric($planId)) {
                continue;
            }
            $plan = $this->db->table($plansTable)
                ->where($planIdColumn, (int) $planId)
                ->first();
            if ($plan === null) {
                continue;
            }

            $rawStatus = (string) ($this->property($subscription, $statusColumn) ?? '');
            $frequency = (string) ($this->property($plan, $planFrequencyColumn) ?? '');
            $normalizedStatus = $this->normalizeStatus($rawStatus, $frequency);
            $accessValidFrom = $this->date($this->property($subscription, $validFromColumn));
            $accessValidUntil = $this->date($this->property($subscription, $validUntilColumn));
            $features = $this->features($plan);
            $sourceRevision = hash('sha256', implode('|', [
                (string) $this->property($subscription, $subscriptionIdColumn),
                (string) $this->property($subscription, $updatedAtColumn),
                (string) $this->property($plan, $planUpdatedAtColumn),
                json_encode($features, JSON_UNESCAPED_SLASHES) ?: '{}',
            ]));

            $snapshot = new EffectiveSubscriptionSnapshot(
                subscriptionId: (string) $this->property($subscription, $subscriptionIdColumn),
                planId: (string) $this->property($plan, $planIdColumn),
                status: $normalizedStatus,
                features: $features,
                accessValidUntil: $accessValidUntil,
                sourceRevision: $sourceRevision,
                accessValidFrom: $accessValidFrom,
                metadata: [
                    'source' => 'magicai',
                    'raw_status' => $rawStatus,
                    'frequency' => $frequency,
                    'source_revision' => $sourceRevision,
                    'owner_user_id' => (int) $ownerUserId,
                ],
            );
            if (! $snapshot->grantsAccessAt($at)) {
                continue;
            }

            $rank = $normalizedStatus === 'lifetime'
                ? PHP_INT_MAX
                : ($accessValidUntil?->getTimestamp() ?? 0);
            if ($rank > $bestRank) {
                $best = $snapshot;
                $bestRank = $rank;
            }
        }

        return $best ?? $this->noSubscription($companyId);
    }

    private function normalizeStatus(string $rawStatus, string $frequency): string
    {
        if (str_starts_with(strtolower(trim($frequency)), 'lifetime')) {
            return 'lifetime';
        }

        $status = strtolower(trim($rawStatus));
        $statusMap = (array) ($this->config['status_map'] ?? []);
        $normalized = $statusMap[$status] ?? null;
        if (is_string($normalized) && $normalized !== '') {
            return strtolower(trim($normalized));
        }

        return $status === 'trialing' ? 'trial' : 'active';
    }

    /** @return array<string,mixed> */
    private function features(object $plan): array
    {
        $features = [];
        foreach ((array) ($this->config['feature_keys'] ?? []) as $featureKey) {
            if (! is_string($featureKey) || trim($featureKey) === '') {
                continue;
            }
            $features[$featureKey] = $this->property($plan, $featureKey) ?? false;
        }
        ksort($features);

        return $features;
    }

    private function noSubscription(int $companyId): EffectiveSubscriptionSnapshot
    {
        $features = [];
        foreach ((array) ($this->config['feature_keys'] ?? []) as $featureKey) {
            if (is_string($featureKey) && trim($featureKey) !== '') {
                $features[$featureKey] = false;
            }
        }
        ksort($features);

        return new EffectiveSubscriptionSnapshot(
            subscriptionId: 'none',
            planId: 'none',
            status: 'expired',
            features: $features,
            accessValidUntil: null,
            sourceRevision: hash('sha256', 'magicai:none:' . $companyId),
            metadata: ['source' => 'magicai', 'reason' => 'no_effective_subscription'],
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function property(object $record, string $name): mixed
    {
        return property_exists($record, $name) ? $record->{$name} : null;
    }

    private function identifier(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("Unsafe MagicAI subscription schema identifier [{$value}].");
        }

        return $value;
    }
}
