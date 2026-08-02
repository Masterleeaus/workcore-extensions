<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveRecordValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureEventProcessor;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;
use RuntimeException;

final class CreateAdaptiveRecord implements BusinessActionHandlerContract
{
    public function __construct(
        private ExpansionRepositoryContract $repository,
        private AdaptiveRecordValidator $records,
        private AdaptiveFeatureEventProcessor $features,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $identifier = trim((string) ($request->payload['domain'] ?? ''));
        if ($identifier === '') {
            throw new InvalidArgumentException('Adaptive domain identifier is required.');
        }
        $domain = $this->repository->findAdaptiveDomain($request->companyId, $identifier, true)
            ?? throw new RuntimeException('Active adaptive domain was not found.');
        $data = $this->records->validate((array) $domain['blueprint'], (array) ($request->payload['data'] ?? []), $request->companyId);
        $statuses = (array) ($domain['blueprint']['statuses'] ?? ['active']);
        $status = (string) ($request->payload['status'] ?? ($statuses[0] ?? 'active'));
        if (! in_array($status, $statuses, true)) {
            throw new InvalidArgumentException("Adaptive record status [{$status}] is not allowed by the domain blueprint.");
        }
        $record = $this->repository->createAdaptiveRecord($request->companyId, $request->actorId, $domain, $data, $status);
        $processing = $this->features->process(
            $request->companyId,
            $request->actorId,
            'workcore.adaptive_record.created',
            'adaptive_record',
            (string) $record['public_id'],
            [
                'record' => $record,
                'event' => ['id' => $request->idempotencyKey, 'name' => 'workcore.adaptive_record.created'],
            ],
        );
        $current = $this->repository->findAdaptiveRecord($request->companyId, (string) $record['public_id']) ?? $record;
        $current['feature_processing'] = ['runs' => $processing['runs']];

        return new ActionHandlerResult(
            data: $current,
            aggregate: new TypedReference('adaptive_record', (string) $record['public_id']),
            events: [
                new PendingDomainEvent('workcore.adaptive_record.created', 1, [
                    'record_public_id' => $record['public_id'],
                    'domain_public_id' => $domain['public_id'],
                    'domain_slug' => $domain['slug'],
                    'status' => $status,
                ]),
                ...$processing['events'],
            ],
        );
    }
}
