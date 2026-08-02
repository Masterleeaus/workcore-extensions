<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult, ActionRequest, PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\References\TypedReference;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\WorkOrderComplianceGate;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class IssueWorkOrderCertificate implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private CompanyVerticalProfileResolver $profiles,
        private WorkOrderComplianceGate $tradeCompliance,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $verticalKey = (string) $request->payload['vertical_key'];
        if (! in_array($verticalKey, ['plumbing', 'electrical'], true)) {
            throw new InvalidArgumentException('Work-order certificates are currently supported for plumbing and electrical verticals.');
        }
        if (! $this->profiles->resolve($request->companyId)->hasPack($verticalKey)) {
            throw new DomainException("Vertical [{$verticalKey}] is not active for the company.");
        }

        $workOrder = $this->record('tz_work_orders', (string) $request->payload['work_order_public_id'], $request->companyId);
        $worker = $this->record('tz_workers', (string) $request->payload['worker_public_id'], $request->companyId);
        $licence = $this->record('tz_trade_licences', (string) $request->payload['trade_licence_public_id'], $request->companyId);
        if ((int) $licence->worker_id !== (int) $worker->id || (string) $licence->vertical_key !== $verticalKey) {
            throw new InvalidArgumentException('Trade licence does not match the issuing worker and vertical.');
        }
        if ((string) $licence->status !== 'valid' || ($licence->expires_on !== null && (string) $licence->expires_on < now()->toDateString())) {
            throw new DomainException('A current valid trade licence is required to issue the certificate.');
        }

        $businessLineId = $this->assertBusinessLineCompatibility(
            request: $request,
            workOrder: $workOrder,
            worker: $worker,
            licence: $licence,
            verticalKey: $verticalKey,
        );

        $this->tradeCompliance->satisfyFirstByType(
            $request->companyId,
            (int) $workOrder->id,
            'licence',
            'trade_licence',
            (string) $licence->public_id,
            $request->actorId,
        );
        $this->tradeCompliance->assertReadyForCertificateIssue($request->companyId, (int) $workOrder->id);

        $certificateNumber = trim((string) $request->payload['certificate_number']);
        if ($this->db->table('tz_work_order_certificates')
            ->where('company_id', $request->companyId)
            ->where('certificate_number', $certificateNumber)
            ->exists()) {
            throw new DomainException('Certificate number is already registered for this company.');
        }

        $publicId = (string) Str::ulid();
        $issuedAt = $request->payload['issued_at'] ?? now();
        $now = now();
        $this->db->table('tz_work_order_certificates')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'work_order_id' => (int) $workOrder->id,
            'business_line_id' => $businessLineId,
            'worker_id' => (int) $worker->id,
            'trade_licence_id' => (int) $licence->id,
            'vertical_key' => $verticalKey,
            'certificate_type' => trim((string) $request->payload['certificate_type']),
            'certificate_number' => $certificateNumber,
            'status' => 'issued',
            'issued_at' => $issuedAt,
            'test_results' => json_encode((array) $request->payload['test_results'], JSON_THROW_ON_ERROR),
            'declaration' => json_encode((array) ($request->payload['declaration'] ?? []), JSON_THROW_ON_ERROR),
            'document_path' => $request->payload['document_path'] ?? null,
            'notes' => $request->payload['notes'] ?? null,
            'created_by_user_id' => $request->actorId,
            'updated_by_user_id' => $request->actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->tradeCompliance->satisfyFirstByType(
            $request->companyId,
            (int) $workOrder->id,
            'certificate',
            'work_order_certificate',
            $publicId,
            $request->actorId,
        );

        $record = (array) $this->db->table('tz_work_order_certificates')
            ->where('company_id', $request->companyId)
            ->where('public_id', $publicId)
            ->first();
        foreach (['test_results', 'declaration'] as $field) {
            $record[$field] = $this->decode($record[$field] ?? null);
        }

        return new ActionHandlerResult(
            data: $record,
            aggregate: new TypedReference('work_order_certificate', $publicId),
            events: [new PendingDomainEvent('workcore.work_order_certificate.issued', 1, [
                'certificate' => $record,
                'work_order_public_id' => (string) $workOrder->public_id,
            ])],
        );
    }

    private function record(string $table, string $publicId, int $companyId): object
    {
        $query = $this->db->table($table)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId);
        if (in_array($table, ['tz_work_orders', 'tz_workers', 'tz_trade_licences'], true)) {
            $query->whereNull('deleted_at');
        }
        $record = $query->first();
        if ($record === null) {
            throw new InvalidArgumentException('Referenced record does not belong to the active company.');
        }

        return $record;
    }

    private function assertBusinessLineCompatibility(
        ActionRequest $request,
        object $workOrder,
        object $worker,
        object $licence,
        string $verticalKey,
    ): ?int {
        $workOrderLineId = $workOrder->business_line_id !== null ? (int) $workOrder->business_line_id : null;
        if ($workOrderLineId !== null) {
            $workOrderLine = $this->db->table('tz_business_lines as bl')
                ->leftJoin('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
                ->where('bl.company_id', $request->companyId)
                ->where('bl.id', $workOrderLineId)
                ->where('bl.status', 'active')
                ->where('cv.status', 'active')
                ->whereNull('bl.deleted_at')
                ->first(['bl.id', 'cv.vertical_key']);
            if ($workOrderLine === null || (string) $workOrderLine->vertical_key !== $verticalKey) {
                throw new DomainException('Work order business line does not belong to the certificate trade vertical.');
            }
        }

        $requestedPublicId = trim((string) ($request->payload['business_line_public_id'] ?? ''));
        $certificateLineId = $workOrderLineId;
        if ($requestedPublicId !== '') {
            $line = $this->db->table('tz_business_lines as bl')
                ->leftJoin('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
                ->where('bl.company_id', $request->companyId)
                ->where('bl.public_id', $requestedPublicId)
                ->where('bl.status', 'active')
                ->where('cv.status', 'active')
                ->whereNull('bl.deleted_at')
                ->first(['bl.id', 'cv.vertical_key']);
            if ($line === null || (string) $line->vertical_key !== $verticalKey) {
                throw new InvalidArgumentException('Business line must belong to the certificate trade vertical.');
            }
            if ($workOrderLineId !== null && $workOrderLineId !== (int) $line->id) {
                throw new DomainException('Certificate business line must match the work order business line.');
            }
            $certificateLineId = (int) $line->id;
        }

        if ($licence->business_line_id !== null && (int) $licence->business_line_id !== (int) $certificateLineId) {
            throw new DomainException('Trade licence business line must match the certificate business line.');
        }

        if ($certificateLineId !== null && ! $this->db->table('tz_worker_business_lines')
            ->where('company_id', $request->companyId)
            ->where('worker_id', (int) $worker->id)
            ->where('business_line_id', $certificateLineId)
            ->where('active', true)
            ->exists()) {
            throw new DomainException('The issuing worker must have an active assignment to the certificate business line.');
        }

        return $certificateLineId;
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
