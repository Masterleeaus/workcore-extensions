<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use Throwable;

final class AdaptiveFeatureEventProcessor
{
    public function __construct(
        private AdaptiveFeatureRepositoryContract $features,
        private ExpansionRepositoryContract $expansion,
        private AdaptiveFeatureSimulator $simulator,
        private AdaptiveFeatureLoopGuard $loopGuard,
        private AdaptiveRecordValidator $records,
        private AdaptiveStatusTransitionValidator $transitions,
    ) {}

    /**
     * @param array<string,mixed> $context
     * @return array{runs:array<int,array<string,mixed>>,events:array<int,PendingDomainEvent>}
     */
    public function process(
        int $companyId,
        int $actorId,
        string $event,
        string $subjectType,
        string $subjectPublicId,
        array $context,
        int $executionDepth = 0,
    ): array {
        $contextDepth = (int) (($context['event']['adaptive_feature_execution_depth'] ?? $context['adaptive_feature_execution_depth'] ?? 0));
        $executionDepth = max(0, $executionDepth, $contextDepth);
        $context['actor'] = array_replace(['id' => (string) $actorId], (array) ($context['actor'] ?? []));
        $context['event'] = array_replace(['name' => $event], (array) ($context['event'] ?? []));
        $context['event']['adaptive_feature_execution_depth'] = $executionDepth;
        $domain = $this->domainKey($context);
        $features = $this->features->activeFeaturesForEvent($companyId, $event, $subjectType, $domain);
        $runs = [];
        $events = [];

        foreach ($features as $feature) {
            $run = null;
            try {
                $blueprint = (array) ($feature['blueprint'] ?? []);
                $idempotencyKey = $this->loopGuard->idempotencyKey(
                    (string) $feature['public_id'],
                    $event,
                    $subjectPublicId,
                    $context,
                    (int) ($feature['version'] ?? 1),
                );
                $existing = $this->features->findRunByIdempotency($companyId, $idempotencyKey);
                if ($existing !== null) {
                    $runs[] = [...$existing, 'replayed' => true];
                    continue;
                }

                $stats = $this->features->executionStats($companyId, $feature, $subjectPublicId);
                $this->loopGuard->assertWithinLimits(
                    $blueprint,
                    $executionDepth,
                    (int) $stats['executions_last_hour'],
                    $stats['last_executed_at'],
                );
                $simulation = $this->simulator->simulate($blueprint, $context, $executionDepth);
                $run = $this->features->startRun(
                    $companyId,
                    $actorId,
                    $feature,
                    $event,
                    $subjectType,
                    $subjectPublicId,
                    $idempotencyKey,
                    $executionDepth,
                    $context,
                    $simulation,
                );

                if (($simulation['status'] ?? null) !== 'matched') {
                    $runs[] = $this->features->completeRun($companyId, (string) $run['public_id'], [
                        'reason' => 'conditions_not_met',
                        'effects_applied' => 0,
                    ], 'skipped');
                    continue;
                }

                $result = $this->applyEffects(
                    $companyId,
                    $actorId,
                    $subjectType,
                    $subjectPublicId,
                    (array) ($simulation['effects'] ?? []),
                    $feature,
                    $run,
                    $executionDepth,
                );
                array_push($events, ...$result['events']);
                $runs[] = $this->features->completeRun($companyId, (string) $run['public_id'], [
                    'effects_applied' => count((array) ($simulation['effects'] ?? [])),
                    'record_public_id' => $result['record']['public_id'] ?? null,
                    'emitted_event_count' => count($result['events']),
                ]);
            } catch (Throwable $exception) {
                if (is_array($run) && isset($run['public_id'])) {
                    try {
                        $runs[] = $this->features->failRun($companyId, (string) $run['public_id'], $exception->getMessage());
                    } catch (Throwable) {
                        $runs[] = ['public_id' => $run['public_id'], 'status' => 'failed', 'error_message' => $exception->getMessage()];
                    }
                } else {
                    $runs[] = [
                        'feature_public_id' => $feature['public_id'] ?? null,
                        'status' => 'rejected_before_start',
                        'error_message' => $exception->getMessage(),
                    ];
                }
                $events[] = new PendingDomainEvent('workcore.adaptive_feature.failed', 1, [
                    'feature_public_id' => $feature['public_id'] ?? null,
                    'subject_type' => $subjectType,
                    'subject_public_id' => $subjectPublicId,
                    'error' => substr($exception->getMessage(), 0, 1000),
                    'adaptive_feature_execution_depth' => $executionDepth + 1,
                ]);
            }
        }

        return ['runs' => $runs, 'events' => $events];
    }

    /**
     * @param array<int,array<string,mixed>> $effects
     * @param array<string,mixed> $feature
     * @param array<string,mixed> $run
     * @return array{record:?array<string,mixed>,events:array<int,PendingDomainEvent>}
     */
    private function applyEffects(
        int $companyId,
        int $actorId,
        string $subjectType,
        string $subjectPublicId,
        array $effects,
        array $feature,
        array $run,
        int $executionDepth,
    ): array {
        $record = $subjectType === 'adaptive_record'
            ? $this->expansion->findAdaptiveRecord($companyId, $subjectPublicId)
            : null;
        $events = [];

        foreach ($effects as $effect) {
            $type = (string) ($effect['type'] ?? '');
            if (in_array($type, ['set_field', 'transition_status'], true)) {
                if ($record === null) {
                    throw new \RuntimeException("Adaptive feature effect [{$type}] requires an adaptive-record subject.");
                }
                $domain = (array) ($record['domain'] ?? []);
                $data = (array) ($record['data'] ?? []);
                $status = (string) ($record['status'] ?? '');
                if ($type === 'set_field') {
                    $data[(string) $effect['field']] = $effect['value'] ?? null;
                    $data = $this->records->validate((array) ($domain['blueprint'] ?? []), $data, $companyId);
                } else {
                    $status = $this->transitions->validate(
                        (array) ($domain['blueprint'] ?? []),
                        $status,
                        (string) ($effect['status'] ?? ''),
                    );
                }
                $record = $this->expansion->updateAdaptiveRecord($companyId, $actorId, $record, $data, $status);
                continue;
            }

            if ($type === 'notify') {
                $events[] = new PendingDomainEvent('workcore.notification.intent.requested', 1, [
                    'notification_key' => $effect['notification_key'],
                    'recipient_type' => $effect['recipient_type'],
                    'recipient_id' => $effect['recipient_id'],
                    'channel_hints' => $effect['channel_hints'] ?? [],
                    'payload' => $effect['payload'] ?? [],
                    'feature_public_id' => $feature['public_id'] ?? null,
                    'feature_run_public_id' => $run['public_id'] ?? null,
                    'adaptive_feature_execution_depth' => $executionDepth + 1,
                ]);
                continue;
            }

            if ($type === 'invoke_action') {
                $events[] = new PendingDomainEvent('workcore.adaptive_feature.action_requested', 1, [
                    'action_key' => $effect['action_key'],
                    'payload' => $effect['payload'] ?? [],
                    'feature_public_id' => $feature['public_id'] ?? null,
                    'feature_run_public_id' => $run['public_id'] ?? null,
                    'confirmation_required' => true,
                    'adaptive_feature_execution_depth' => $executionDepth + 1,
                ]);
                continue;
            }

            if ($type === 'emit_event') {
                $events[] = new PendingDomainEvent((string) $effect['event'], 1, [
                    ...(array) ($effect['payload'] ?? []),
                    'feature_public_id' => $feature['public_id'] ?? null,
                    'feature_run_public_id' => $run['public_id'] ?? null,
                    'adaptive_feature_execution_depth' => $executionDepth + 1,
                ]);
            }
        }

        return ['record' => $record, 'events' => $events];
    }

    /** @param array<string,mixed> $context */
    private function domainKey(array $context): ?string
    {
        $domain = (array) ($context['record']['domain'] ?? []);
        $value = (string) ($domain['slug'] ?? $domain['capability_key'] ?? $context['domain'] ?? '');
        return trim($value) !== '' ? $value : null;
    }
}
