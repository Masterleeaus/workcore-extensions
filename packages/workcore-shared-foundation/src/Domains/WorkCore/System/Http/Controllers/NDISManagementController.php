<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Controllers;

use App\Domains\WorkCore\System\Actions\{ActionRequest,ActionResult,BusinessActionDispatcher};
use App\Domains\WorkCore\System\Api\ApiResponseFactory;
use App\Domains\WorkCore\System\Http\Requests\{
    AddNDISCaseNoteRequest,
    LinkNDISParticipantIncidentRequest,
    MatchNDISWorkerRequest,
    PrepareNDISClaimLineRequest,
    RecordNDISSupportDeliveryRequest,
    UpsertNDISGoalRequest,
    UpsertNDISParticipantContactRequest,
    UpsertNDISParticipantRequest,
    UpsertNDISPlanRequest,
    UpsertNDISServiceAgreementRequest
};
use App\Domains\WorkCore\System\Modules\NDIS\Application\ReadModels\{
    GetNDISBudgetPosition,
    GetNDISParticipantProfile,
    ListNDISClaimQueue
};
use Illuminate\Http\{JsonResponse,Request};

final class NDISManagementController
{
    public function profile(string $participant, GetNDISParticipantProfile $read, ApiResponseFactory $api): JsonResponse
    {
        return $api->success($read->execute($participant));
    }

    public function budgetPosition(string $participant, Request $request, GetNDISBudgetPosition $read, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate(['plan_public_id' => ['nullable','string','size:26']]);
        return $api->success($read->execute($participant, $validated['plan_public_id'] ?? null));
    }

    public function claims(Request $request, ListNDISClaimQueue $read, ApiResponseFactory $api): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable','in:prepared,held,exported,accepted,rejected,reconciled,voided'],
            'limit' => ['nullable','integer','min:1','max:500'],
        ]);
        return $api->success($read->execute($validated['status'] ?? null, (int) ($validated['limit'] ?? 200)));
    }

    public function upsertParticipant(?string $participant, UpsertNDISParticipantRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $payload = $request->validated();
        if ($participant !== null && $participant !== '') $payload = ['participant_public_id' => $participant] + $payload;
        return $this->dispatch('workcore.ndis.participant.upsert', $payload, $request, $dispatcher, $api);
    }

    public function upsertContact(string $participant, ?string $contact, UpsertNDISParticipantContactRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $payload = ['participant_public_id' => $participant] + $request->validated();
        if ($contact !== null && $contact !== '') $payload = ['contact_public_id' => $contact] + $payload;
        return $this->dispatch('workcore.ndis.contact.upsert', $payload, $request, $dispatcher, $api);
    }

    public function upsertPlan(string $participant, ?string $plan, UpsertNDISPlanRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $payload = ['participant_public_id' => $participant] + $request->validated();
        if ($plan !== null && $plan !== '') $payload = ['plan_public_id' => $plan] + $payload;
        return $this->dispatch('workcore.ndis.plan.upsert', $payload, $request, $dispatcher, $api);
    }

    public function upsertGoal(string $participant, ?string $goal, UpsertNDISGoalRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $payload = ['participant_public_id' => $participant] + $request->validated();
        if ($goal !== null && $goal !== '') $payload = ['goal_public_id' => $goal] + $payload;
        return $this->dispatch('workcore.ndis.goal.upsert', $payload, $request, $dispatcher, $api);
    }

    public function upsertAgreement(string $participant, ?string $agreement, UpsertNDISServiceAgreementRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        $payload = ['participant_public_id' => $participant] + $request->validated();
        if ($agreement !== null && $agreement !== '') $payload = ['agreement_public_id' => $agreement] + $payload;
        return $this->dispatch('workcore.ndis.agreement.upsert', $payload, $request, $dispatcher, $api);
    }

    public function recordDelivery(string $participant, RecordNDISSupportDeliveryRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.ndis.delivery.record', ['participant_public_id' => $participant] + $request->validated(), $request, $dispatcher, $api, 201);
    }

    public function addCaseNote(string $participant, AddNDISCaseNoteRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.ndis.case_note.add', ['participant_public_id' => $participant] + $request->validated(), $request, $dispatcher, $api, 201);
    }

    public function linkIncident(string $participant, LinkNDISParticipantIncidentRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.ndis.incident.link', ['participant_public_id' => $participant] + $request->validated(), $request, $dispatcher, $api, 201);
    }

    public function matchWorker(string $participant, MatchNDISWorkerRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.ndis.worker.match', ['participant_public_id' => $participant] + $request->validated(), $request, $dispatcher, $api);
    }

    public function prepareClaim(string $participant, PrepareNDISClaimLineRequest $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api): JsonResponse
    {
        return $this->dispatch('workcore.ndis.claim.prepare', ['participant_public_id' => $participant] + $request->validated(), $request, $dispatcher, $api, 201);
    }

    /** @param array<string,mixed> $payload */
    private function dispatch(string $key, array $payload, Request $request, BusinessActionDispatcher $dispatcher, ApiResponseFactory $api, int $status = 200): JsonResponse
    {
        $tenant = workcore_tenant();
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        abort_if($idempotencyKey === '', 422, 'Idempotency-Key header is required.');
        $result = $dispatcher->dispatch(new ActionRequest(
            $key, $payload, (int) $tenant->companyId(), (int) $tenant->userId(), $idempotencyKey,
            $request->header('X-WorkCore-Confirmation'), 'api',
        ));
        return $api->action(new ActionResult(
            $result->actionKey, $result->data, $result->correlationId, $result->auditId, $result->eventIds, $result->replayed,
        ), $status);
    }
}
