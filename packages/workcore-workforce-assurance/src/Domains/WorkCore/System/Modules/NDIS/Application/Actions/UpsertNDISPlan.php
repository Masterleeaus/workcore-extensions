<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\PlanState;
use App\Domains\WorkCore\System\References\TypedReference;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class UpsertNDISPlan implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $status = (string) ($payload['status'] ?? 'draft');
        PlanState::assertAllowed($status);

        $starts = new DateTimeImmutable((string) $payload['starts_on']);
        $ends = new DateTimeImmutable((string) $payload['ends_on']);
        if ($ends < $starts) {
            throw new DomainException('NDIS plan end date must not precede its start date.');
        }

        $funding = (string) ($payload['funding_management'] ?? $participant->funding_management ?? 'unknown');
        if (! in_array($funding, ['agency_managed', 'plan_managed', 'self_managed', 'mixed', 'unknown'], true)) {
            throw new DomainException('Unsupported plan funding-management type.');
        }

        $publicId = trim((string) ($payload['plan_public_id'] ?? '')) ?: (string) Str::ulid();
        $result = $this->db->transaction(function () use ($request, $payload, $participant, $status, $funding, $publicId): array {
            $existing = $this->db->table('tz_ndis_plans')
                ->where('company_id', $request->companyId)
                ->where('public_id', $publicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($existing !== null && (int) $existing->participant_id !== (int) $participant->id) {
                throw new DomainException('NDIS plan belongs to another participant.');
            }
            if ($existing !== null && (string) $existing->status !== $status && ! PlanState::canTransition((string) $existing->status, $status)) {
                throw new DomainException("NDIS plan cannot transition from [{$existing->status}] to [{$status}].");
            }

            $planManagerId = null;
            if (! empty($payload['plan_manager_contact_public_id'])) {
                $contact = $this->records->contact($request->companyId, (string) $payload['plan_manager_contact_public_id']);
                if ((int) $contact->participant_id !== (int) $participant->id) {
                    throw new DomainException('Plan manager contact belongs to another participant.');
                }
                $planManagerId = (int) $contact->id;
            }

            $totalBudget = round((float) ($payload['total_budget'] ?? $existing->total_budget ?? 0), 4);
            if ($totalBudget < 0) {
                throw new DomainException('NDIS plan total budget cannot be negative.');
            }

            $budgets = (array) ($payload['budgets'] ?? []);
            $allocatedTotal = 0.0;
            foreach ($budgets as $budget) {
                $allocated = round((float) ($budget['allocated_amount'] ?? 0), 4);
                $committed = round((float) ($budget['committed_amount'] ?? 0), 4);
                $spent = round((float) ($budget['spent_amount'] ?? 0), 4);
                if ($allocated < 0 || $committed < 0 || $spent < 0) {
                    throw new DomainException('NDIS plan budget amounts cannot be negative.');
                }
                if ($committed > $allocated || $spent > $allocated) {
                    throw new DomainException('Committed and spent amounts cannot exceed the category allocation.');
                }
                $allocatedTotal = round($allocatedTotal + $allocated, 4);
            }
            if ($totalBudget > 0 && $allocatedTotal > $totalBudget) {
                throw new DomainException('Budget allocations cannot exceed the plan total budget.');
            }

            $values = [
                'company_id' => $request->companyId,
                'participant_id' => (int) $participant->id,
                'plan_manager_contact_id' => $planManagerId,
                'plan_number' => trim((string) ($payload['plan_number'] ?? $existing->plan_number ?? '')),
                'status' => $status,
                'starts_on' => (string) $payload['starts_on'],
                'ends_on' => (string) $payload['ends_on'],
                'review_on' => $payload['review_on'] ?? ($existing->review_on ?? null),
                'funding_management' => $funding,
                'currency' => strtoupper((string) ($payload['currency'] ?? $existing->currency ?? 'AUD')),
                'total_budget' => $totalBudget,
                'notes' => $payload['notes'] ?? ($existing->notes ?? null),
                'metadata' => json_encode((array) ($payload['metadata'] ?? $this->records->decode($existing->metadata ?? null)), JSON_THROW_ON_ERROR),
                'updated_by_user_id' => $request->actorId,
                'updated_at' => now(),
            ];
            if ($values['plan_number'] === '') {
                throw new DomainException('NDIS plan number is required.');
            }

            if ($existing === null) {
                $this->db->table('tz_ndis_plans')->insert($values + [
                    'public_id' => $publicId,
                    'created_by_user_id' => $request->actorId,
                    'created_at' => now(),
                ]);
                $planId = (int) $this->db->table('tz_ndis_plans')
                    ->where('company_id', $request->companyId)
                    ->where('public_id', $publicId)
                    ->value('id');
            } else {
                $planId = (int) $existing->id;
                $this->db->table('tz_ndis_plans')->where('id', $planId)->update($values);
            }

            if ($status === 'active') {
                $this->db->table('tz_ndis_plans')
                    ->where('company_id', $request->companyId)
                    ->where('participant_id', (int) $participant->id)
                    ->where('id', '!=', $planId)
                    ->where('status', 'active')->update(['status' => 'superseded', 'updated_at' => now()]);
            }

            $budgetPublicIds = [];
            foreach ($budgets as $budget) {
                $code = trim((string) ($budget['category_code'] ?? ''));
                if ($code === '') {
                    throw new DomainException('Every NDIS plan budget requires a category code.');
                }

                $budgetPublicId = trim((string) ($budget['public_id'] ?? '')) ?: (string) Str::ulid();
                $budgetValues = [
                    'company_id' => $request->companyId,
                    'public_id' => $budgetPublicId,
                    'plan_id' => $planId,
                    'category_code' => $code,
                    'category_name' => trim((string) ($budget['category_name'] ?? $code)),
                    'support_purpose' => (string) ($budget['support_purpose'] ?? 'core'),
                    'allocated_amount' => round((float) ($budget['allocated_amount'] ?? 0), 4),
                    'committed_amount' => round((float) ($budget['committed_amount'] ?? 0), 4),
                    'spent_amount' => round((float) ($budget['spent_amount'] ?? 0), 4),
                    'flexible' => (bool) ($budget['flexible'] ?? false),
                    'metadata' => json_encode((array) ($budget['metadata'] ?? []), JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ];
                $current = $this->db->table('tz_ndis_plan_budgets')
                    ->where('plan_id', $planId)
                    ->where('category_code', $code)
                    ->first();
                if ($current === null) {
                    $this->db->table('tz_ndis_plan_budgets')->insert($budgetValues + ['created_at' => now()]);
                } else {
                    $budgetPublicId = (string) $current->public_id;
                    unset($budgetValues['public_id']);
                    $this->db->table('tz_ndis_plan_budgets')->where('id', (int) $current->id)->update($budgetValues);
                }
                $budgetPublicIds[] = $budgetPublicId;
            }

            $persistedAllocatedTotal = round((float) $this->db->table('tz_ndis_plan_budgets')
                ->where('company_id', $request->companyId)
                ->where('plan_id', $planId)
                ->sum('allocated_amount'), 4);
            if ($totalBudget > 0 && $persistedAllocatedTotal > $totalBudget) {
                throw new DomainException('Budget allocations cannot exceed the plan total budget.');
            }

            return ['budget_public_ids' => $budgetPublicIds];
        }, 3);

        $data = [
            'public_id' => $publicId,
            'participant_public_id' => (string) $participant->public_id,
            'status' => $status,
            'funding_management' => $funding,
            'budget_public_ids' => $result['budget_public_ids'],
        ];

        return new ActionHandlerResult(
            $data,
            new TypedReference('ndis_plan', $publicId),
            [new PendingDomainEvent('workcore.ndis.plan.upserted', 1, $data)],
        );
    }
}
