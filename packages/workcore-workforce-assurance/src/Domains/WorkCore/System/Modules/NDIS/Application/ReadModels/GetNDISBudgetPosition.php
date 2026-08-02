<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\ReadModels;

use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class GetNDISBudgetPosition
{
    public function __construct(private TenantContextContract $tenant, private ConnectionInterface $db, private PermissionResolverContract $permissions) {}

    /** @return array<string,mixed> */
    public function execute(string $participantPublicId, ?string $planPublicId = null, ?int $companyId = null): array
    {
        $companyId ??= $this->tenant->companyId();
        $userId = $this->tenant->userId();
        if ($userId === null || ! $this->permissions->allows($userId, $companyId, (string) config('workcore.ndis.permissions.view'))) {
            throw new AuthorizationException('NDIS budget view permission is required.');
        }
        $participant = $this->db->table('tz_ndis_participants')->where('company_id', $companyId)->where('public_id', $participantPublicId)->whereNull('deleted_at')->first()
            ?? throw new InvalidArgumentException('NDIS participant does not belong to the active company.');
        $planQuery = $this->db->table('tz_ndis_plans')->where('company_id', $companyId)->where('participant_id', $participant->id)->whereNull('deleted_at');
        if ($planPublicId !== null && trim($planPublicId) !== '') $planQuery->where('public_id', $planPublicId);
        else $planQuery->where('status', 'active')->orderByDesc('starts_on');
        $plan = $planQuery->first() ?? throw new InvalidArgumentException('No matching NDIS plan is available.');
        $claimed = $this->db->table('tz_ndis_claim_lines')->where('company_id', $companyId)->where('plan_id', $plan->id)
            ->whereNull('deleted_at')->whereNotIn('status', ['voided','rejected'])->selectRaw('plan_budget_id, SUM(amount) as amount')->groupBy('plan_budget_id')->pluck('amount', 'plan_budget_id');
        $delivered = $this->db->table('tz_ndis_support_deliveries as d')->join('tz_ndis_service_agreement_items as i', 'i.id', '=', 'd.agreement_item_id')
            ->where('d.company_id', $companyId)->where('d.plan_id', $plan->id)->whereNull('d.deleted_at')->whereNotIn('d.status', ['voided'])
            ->selectRaw('i.plan_budget_id, SUM(d.claimable_amount + d.travel_amount) as amount')->groupBy('i.plan_budget_id')->pluck('amount', 'i.plan_budget_id');
        $budgets = $this->db->table('tz_ndis_plan_budgets')->where('company_id', $companyId)->where('plan_id', $plan->id)->orderBy('category_name')->get()->map(function (object $row) use ($claimed, $delivered): array {
            $claimAmount = round((float) ($claimed[$row->id] ?? 0), 4); $deliveryAmount = round((float) ($delivered[$row->id] ?? 0), 4);
            $allocated = round((float) $row->allocated_amount, 4); $committed = max(round((float) $row->committed_amount, 4), $deliveryAmount);
            return [
                'public_id' => $row->public_id, 'category_code' => $row->category_code, 'category_name' => $row->category_name,
                'support_purpose' => $row->support_purpose, 'allocated_amount' => $allocated,
                'committed_amount' => $committed, 'delivered_amount' => $deliveryAmount, 'claimed_amount' => $claimAmount,
                'available_amount' => round($allocated - max($committed, $claimAmount), 4), 'flexible' => (bool) $row->flexible,
            ];
        })->all();
        return ['participant_public_id' => $participantPublicId, 'plan' => ['public_id' => $plan->public_id, 'plan_number' => $plan->plan_number, 'status' => $plan->status, 'starts_on' => $plan->starts_on, 'ends_on' => $plan->ends_on, 'currency' => $plan->currency], 'budgets' => $budgets];
    }
}
