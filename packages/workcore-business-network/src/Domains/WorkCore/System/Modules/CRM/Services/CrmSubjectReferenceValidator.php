<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Services;

use App\Domains\WorkCore\System\References\RecordTypeRegistry;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Validation\ValidationException;

final class CrmSubjectReferenceValidator
{
    private const TABLES = [
        'lead' => 'tz_leads',
        'customer' => 'tz_customers',
        'contact' => 'tz_customer_contacts',
        'opportunity' => 'tz_opportunities',
    ];

    public function __construct(
        private CompanyScopedReferenceValidator $references,
        private RecordTypeRegistry $types,
    ) {}

    public function require(int $companyId, string $subjectType, string $publicId): void
    {
        if (! isset(self::TABLES[$subjectType]) || ! $this->types->allows($subjectType)) {
            throw ValidationException::withMessages(['subject_type' => ['The selected CRM subject type is not supported.']]);
        }

        $this->references->requirePublicRecordId($companyId, self::TABLES[$subjectType], $publicId, 'subject_public_id');
    }
}
