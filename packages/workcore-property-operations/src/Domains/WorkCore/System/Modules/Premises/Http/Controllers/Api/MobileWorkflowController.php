<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;
use App\Domains\WorkCore\System\Modules\Premises\Services\PremiseWorkflowService;

class MobileWorkflowController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(function_exists('company') && company(), 403);
        $query = PremiseWorkflow::withoutGlobalScopes()
            ->where('company_id', company()->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['premise:id,name,profile_key', 'space:id,name,code', 'occupancy:id,display_name,status'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id');

        if ($request->filled('premise_id')) $query->where('premise_id', (int) $request->integer('premise_id'));
        if ($request->boolean('assigned_to_me', true)) {
            $userId = $request->user()?->id;
            $query->where(fn ($builder) => $builder->whereNull('assigned_user_id')->orWhere('assigned_user_id', $userId));
        }

        return response()->json([
            'data' => $query->limit(min(100, max(1, (int) $request->integer('limit', 50))))->get()->map(fn ($workflow) => $this->resource($workflow))->values(),
            'safety' => [
                'access_secrets_redacted' => true,
                'restricted_incidents_excluded' => true,
                'financial_actions_unavailable' => true,
            ],
        ]);
    }

    public function show(Request $request, PremiseWorkflow $workflow)
    {
        $this->assertScoped($request, $workflow);
        $workflow->load(['premise:id,name,profile_key', 'space:id,name,code', 'occupancy:id,display_name,status']);
        return response()->json(['data' => $this->resource($workflow, true)]);
    }

    public function advance(Request $request, PremiseWorkflow $workflow, PremiseWorkflowService $service)
    {
        $this->assertScoped($request, $workflow);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $updated = $service->advance($workflow, $data['note'] ?? null);
        return response()->json(['data' => $this->resource($updated), 'message' => 'Workflow stage completed.']);
    }

    private function resource(PremiseWorkflow $workflow, bool $includeStages = false): array
    {
        $resource = [
            'id' => $workflow->id,
            'title' => $workflow->title,
            'status' => $workflow->status,
            'profile_key' => $workflow->profile_key,
            'template_key' => $workflow->template_key,
            'premise' => $workflow->premise ? ['id' => $workflow->premise->id, 'name' => $workflow->premise->name] : null,
            'space' => $workflow->space ? ['id' => $workflow->space->id, 'name' => $workflow->space->name, 'code' => $workflow->space->code] : null,
            'occupancy' => $workflow->occupancy ? ['id' => $workflow->occupancy->id, 'display_name' => $workflow->occupancy->display_name, 'status' => $workflow->occupancy->status] : null,
            'current_stage_key' => $workflow->current_stage_key,
            'current_stage_label' => $workflow->currentStageLabel(),
            'completion_percent' => $workflow->completionPercent(),
            'started_at' => optional($workflow->started_at)->toIso8601String(),
            'due_at' => optional($workflow->due_at)->toIso8601String(),
            'is_overdue' => $workflow->due_at ? $workflow->due_at->isPast() : false,
        ];
        if ($includeStages) {
            $resource['stages'] = collect($workflow->stages_snapshot ?? [])->map(fn ($stage) => [
                'key' => $stage['key'] ?? null,
                'label' => $stage['label'] ?? null,
                'completed' => in_array($stage['key'] ?? null, $workflow->completed_stage_keys ?? [], true),
                'current' => ($stage['key'] ?? null) === $workflow->current_stage_key,
            ])->values()->all();
        }
        return $resource;
    }

    private function assertScoped(Request $request, PremiseWorkflow $workflow): void
    {
        abort_unless(function_exists('company') && company() && (int) $workflow->company_id === (int) company()->id, 403);
        $userId = $request->user()?->id;
        abort_if($workflow->assigned_user_id && (int) $workflow->assigned_user_id !== (int) $userId, 403);
    }
}
