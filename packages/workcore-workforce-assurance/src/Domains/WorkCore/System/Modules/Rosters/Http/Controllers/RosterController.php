<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Api\Resources\{AvailabilityRuleResource,RosterResource};
use App\Domains\WorkCore\System\Modules\Rosters\Actions\{SearchAvailability,SearchRosters};
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\Modules\Rosters\Http\Requests\{AssignRosterShiftRequest,PublishRosterRequest,StoreAvailabilityRuleRequest,StoreRosterRequest,StoreRosterShiftRequest};
use Illuminate\Http\{JsonResponse,Request};

final class RosterController
{
    public function index(Request $request, SearchRosters $search, ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated($search->execute(
            $request->string('q')->toString() ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('roster_type')->toString() ?: null,
            $request->string('premises_id')->toString() ?: null,
            $request->string('starts_from')->toString() ?: null,
            $request->string('starts_to')->toString() ?: null,
            $request->integer('per_page', 25),
        ), RosterResource::make(...));
    }

    public function show(string $roster, RosterRepositoryContract $repository, ApiResponseFactory $api): JsonResponse
    {
        $record = $repository->findRosterByPublicId((int) workcore_tenant()->companyId(), $roster);
        abort_if($record === null, 404, 'Roster not found.');
        return $api->success(RosterResource::make($record));
    }

    public function availability(Request $request, SearchAvailability $search, ApiResponseFactory $api): JsonResponse
    {
        return $api->paginated($search->execute(
            $request->string('worker_id')->toString() ?: null,
            $request->string('availability_type')->toString() ?: null,
            $request->integer('weekday') ?: null,
            $request->integer('per_page', 50),
        ), AvailabilityRuleResource::make(...));
    }

    public function storeAvailability(StoreAvailabilityRuleRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.availability_rule.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function store(StoreRosterRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.roster.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function storeShift(StoreRosterShiftRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.roster_shift.create', $request->validated(), $request, $dispatcher, $api);
    }

    public function assignShift(string $shift, AssignRosterShiftRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.roster_shift.assign', ['shift_public_id' => $shift] + $request->validated(), $request, $dispatcher, $api);
    }

    public function publish(string $roster, PublishRosterRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.roster.publish', ['roster_public_id' => $roster] + $request->validated(), $request, $dispatcher, $api);
    }

    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            $key, $payload, (int) $tenant->companyId(), (int) $tenant->userId(), $idempotencyKey,
            $request->header('X-WorkCore-Confirmation'), 'api',
        ));
        return $api->action(new ActionResult($result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed));
    }
}
