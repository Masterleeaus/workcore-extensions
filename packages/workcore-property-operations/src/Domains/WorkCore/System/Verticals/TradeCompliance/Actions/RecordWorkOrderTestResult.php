<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\TradeComplianceActionSupport;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RecordWorkOrderTestResult implements BusinessActionHandlerContract
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
            'test_result',
        );
        $licenceId = $this->optionalLicenceId($request, $worker?->id);
        $calibrationCredentialId = $this->optionalCertificationId($request, $worker?->id);
        $publicId = (string) Str::ulid();
        $result = (string) $request->payload['result'];
        $now = now();
        $this->db->table('tz_work_order_test_results')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'work_order_id' => (int) $workOrder->id,
            'compliance_item_id' => $itemId,
            'worker_id' => $worker?->id,
            'trade_licence_id' => $licenceId,
            'calibration_credential_id' => $calibrationCredentialId,
            'test_type' => trim((string) $request->payload['test_type']),
            'result' => $result,
            'numeric_value' => $request->payload['numeric_value'] ?? null,
            'unit' => $request->payload['unit'] ?? null,
            'readings' => json_encode((array) ($request->payload['readings'] ?? []), JSON_THROW_ON_ERROR),
            'instrument_identifier' => $request->payload['instrument_identifier'] ?? null,
            'tested_at' => $request->payload['tested_at'] ?? $now,
            'notes' => $request->payload['notes'] ?? null,
            'recorded_by_user_id' => $request->actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($result === 'pass') {
            $this->support->satisfy(
                $request->companyId,
                (int) $workOrder->id,
                $request->payload['requirement_code'] ?? null,
                'test_result',
                'work_order_test_result',
                $publicId,
                $request->actorId,
            );
        }
        $record = (array) $this->db->table('tz_work_order_test_results')->where('public_id', $publicId)->first();
        $record['readings'] = (array) ($request->payload['readings'] ?? []);
        return new ActionHandlerResult(
            $record,
            new TypedReference('work_order_test_result', $publicId),
            [new PendingDomainEvent('workcore.trade_compliance.test_result_recorded', 1, ['test_result' => $record])],
        );
    }

    private function optionalLicenceId(ActionRequest $request, ?int $workerId): ?int
    {
        $publicId = trim((string) ($request->payload['trade_licence_public_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }
        $record = $this->db->table('tz_trade_licences')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();
        if ($record === null || ($workerId !== null && (int) $record->worker_id !== $workerId)) {
            throw new InvalidArgumentException('Trade licence does not belong to the selected worker and company.');
        }
        if ((string) $record->status !== 'valid' || ($record->expires_on !== null && (string) $record->expires_on < now()->toDateString())) {
            throw new DomainException('A current valid trade licence is required for this test result.');
        }
        return (int) $record->id;
    }

    private function optionalCertificationId(ActionRequest $request, ?int $workerId): ?int
    {
        $publicId = trim((string) ($request->payload['calibration_credential_public_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }
        $record = $this->db->table('tz_worker_certifications')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();
        if ($record === null || ($workerId !== null && (int) $record->worker_id !== $workerId)) {
            throw new InvalidArgumentException('Calibration credential does not belong to the selected worker and company.');
        }
        if ((string) $record->status !== 'valid' || ($record->expires_on !== null && (string) $record->expires_on < now()->toDateString())) {
            throw new DomainException('Calibration credential is not currently valid.');
        }
        return (int) $record->id;
    }
}
