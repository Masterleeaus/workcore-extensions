<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Applications\EnquiryState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseVacancyEnquiry extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_vacancy_enquiries';
    protected $guarded = ['id'];
    protected $casts = ['consent_at' => 'datetime', 'contacted_at' => 'datetime', 'qualified_at' => 'datetime', 'closed_at' => 'datetime', 'metadata' => 'array'];
    public const STATUSES = EnquiryState::STATUSES;
    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            foreach (['company_id', 'premise_id', 'vacancy_listing_id', 'space_id', 'public_reference'] as $field) {
                if ($record->isDirty($field)) throw new \LogicException("Vacancy enquiry identity is immutable: {$field}.");
            }
            if (EnquiryState::isTerminal((string) $record->getOriginal('status'))) throw new \LogicException('Terminal vacancy enquiry history is immutable.');
        });
    }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function listing(): BelongsTo { return $this->belongsTo(PremiseVacancyListing::class, 'vacancy_listing_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function convertedApplication(): BelongsTo { return $this->belongsTo(PremiseApplication::class, 'converted_application_id'); }
}
