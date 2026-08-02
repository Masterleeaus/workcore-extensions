<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Services;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class OperationalSubjectResolver
{
    /** @var array<string,string> */
    private const TABLES = [
        'customer' => 'tz_customers',
        'contact' => 'tz_contacts',
        'premises' => 'tz_premises',
        'work_order' => 'tz_work_orders',
        'appointment' => 'tz_appointments',
        'asset' => 'tz_assets',
        'worker' => 'tz_workers',
        'service' => 'tz_services',
        'support_ticket' => 'tz_support_tickets',
        'form_submission' => 'tz_form_submissions',
        'form_evidence' => 'tz_form_evidence',
        'work_order_evidence' => 'tz_work_order_evidence',
        'document' => 'tz_documents',
        'document_version' => 'tz_document_versions',
        'evidence' => 'tz_evidence_items',
        'inspection' => 'tz_inspections',
        'inspection_result' => 'tz_inspection_results',
        'finding' => 'tz_assurance_findings',
        'corrective_action' => 'tz_corrective_actions',
        'risk' => 'tz_risk_register',
        'incident' => 'tz_safety_incidents',
    ];

    public function __construct(private ConnectionInterface $db) {}

    public function resolve(int $companyId, string $type, string $publicId): object
    {
        $type = strtolower(trim($type));
        $publicId = trim($publicId);
        $table = self::TABLES[$type] ?? null;
        if ($table === null || $publicId === '') {
            throw new InvalidArgumentException('A supported company-scoped subject type and public id are required.');
        }

        $record = $this->db->table($table)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->first();

        if ($record === null) {
            throw new InvalidArgumentException("Referenced {$type} does not belong to the active company.");
        }

        return $record;
    }

    public function supports(string $type): bool
    {
        return isset(self::TABLES[strtolower(trim($type))]);
    }
}
