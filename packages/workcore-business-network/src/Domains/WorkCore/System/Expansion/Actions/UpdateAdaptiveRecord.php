<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveRecordValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveStatusTransitionValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureEventProcessor;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;
use RuntimeException;

final class UpdateAdaptiveRecord implements BusinessActionHandlerContract
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private AdaptiveRecordValidator $records,
        private AdaptiveStatusTransitionValidator $transitions,
        private AdaptiveFeatureEventProcessor $features,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = trim((string) ($request->payload['record_public_id'] ?? ''));
        if ($publicId === '') {
            throw new InvalidArgumentException('Adaptive record_public_id is required.');
        }
        $record = $this->repository->findAdaptiveRecord($request->companyId, $publicId)
            ?? throw new RuntimeException('Adaptive record was not found.');
        $domain = (array) ($record['domain'] ?? []);
        if ((string) ($domain['status'] ?? '') !== 'active') {
            throw new RuntimeException('Adaptive record domain is not active.');
        }

        $patch = $request->payload['data'] ?? [];
        if (! is_array($patch)) {
            throw new InvalidArgumentException('Adaptive record data patch must be an object.');
        }
        $data = $this->records->validate(
            (array) ($domain['blueprint'] ?? []),
            array_replace((array) ($record['data'] ?? []), $patch),
            $request->companyId,
        );

        $fromStatus = (string) ($record['status'] ?? '');
        $toStatus = isset($request->payload['status'])
            ? (string) $request->payload['status']
            : $fromStatus;
        $toStatus = $this->transitions->validate((array) ($domain['blueprint'] ?? []), $fromStatus, $toStatus);
        $updated = $this->repository->updateAdaptiveRecord($request->companyId, $request->actorId, $record, $data, $toStatus);
        $processing = $this->features->process(
            $request->companyId,
            $request->actorId,
            'workcore.adaptive_record.updated',
            'adaptive_record',
            $publicId,
            [
                'record' => $updated,
                'event' => [
                    'id' => $request->idempotencyKey,
                    'name' => 'workcore.adaptive_record.updated',
                    'changed_fields' => array_values(array_keys($patch)),
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ],
            ],
        );
        $current = $this->repository->findAdaptiveRecord($request->companyId, $publicId) ?? $updated;
        $current['feature_processing'] = ['runs' => $processing['runs']];

        return new ActionHandlerResult(
            data: $current,
            aggregate: new TypedReference('adaptive_record', $publicId),
            events: [
                new PendingDomainEvent('workcore.adaptive_record.updated', 1, [
                    'record_public_id' => $publicId,
                    'domain_public_id' => $domain['public_id'] ?? null,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'changed_fields' => array_values(array_keys($patch)),
                ]),
                ...$processing['events'],
            ],
        );
    }
}
