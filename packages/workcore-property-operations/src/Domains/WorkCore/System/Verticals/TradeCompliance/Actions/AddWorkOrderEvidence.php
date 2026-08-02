<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\TradeComplianceActionSupport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AddWorkOrderEvidence implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private TradeComplianceActionSupport $support) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $workOrder = $this->support->workOrder($request->companyId, (string) $request->payload['work_order_public_id']);
        $worker = $this->support->worker($request->companyId, $request->payload['worker_public_id'] ?? null);
        $itemId = $this->support->complianceItemId(
            $request->companyId,
            (int) $workOrder->id,
            $request->payload['requirement_code'] ?? null,
            'evidence',
        );
        $formSubmissionId = $this->optionalFormSubmissionId($request, (int) $workOrder->id);
        if ($formSubmissionId === null && trim((string) ($request->payload['storage_path'] ?? '')) === '') {
            throw new InvalidArgumentException('A form submission or storage path is required for work-order evidence.');
        }
        $publicId = (string) Str::ulid();
        $now = now();
        $this->db->table('tz_work_order_evidence')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'work_order_id' => (int) $workOrder->id,
            'compliance_item_id' => $itemId,
            'form_submission_id' => $formSubmissionId,
            'worker_id' => $worker?->id,
            'evidence_type' => trim((string) $request->payload['evidence_type']),
            'phase' => (string) ($request->payload['phase'] ?? 'after'),
            'storage_path' => $request->payload['storage_path'] ?? null,
            'content_hash' => $request->payload['content_hash'] ?? null,
            'captured_at' => $request->payload['captured_at'] ?? $now,
            'metadata' => json_encode((array) ($request->payload['metadata'] ?? []), JSON_THROW_ON_ERROR),
            'captured_by_user_id' => $request->actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->support->satisfy(
            $request->companyId,
            (int) $workOrder->id,
            $request->payload['requirement_code'] ?? null,
            'evidence',
            'work_order_evidence',
            $publicId,
            $request->actorId,
        );
        $record = (array) $this->db->table('tz_work_order_evidence')->where('public_id', $publicId)->first();
        $record['metadata'] = (array) ($request->payload['metadata'] ?? []);
        return new ActionHandlerResult(
            $record,
            new TypedReference('work_order_evidence', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.evidence_added', 1, ['evidence' => $record])],
        );
    }

    private function optionalFormSubmissionId(ActionRequest $request, int $workOrderId): ?int
    {
        $publicId = trim((string) ($request->payload['form_submission_public_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }
        $record = $this->db->table('tz_form_submissions')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();
        if ($record === null || ($record->work_order_id !== null && (int) $record->work_order_id !== $workOrderId)) {
            throw new InvalidArgumentException('Form submission does not belong to the selected work order and company.');
        }
        return (int) $record->id;
    }
}
