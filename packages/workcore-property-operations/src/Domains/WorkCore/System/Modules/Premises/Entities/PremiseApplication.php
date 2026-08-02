<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Applications\ApplicationState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseApplication extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_applications';
    protected $guarded = ['id'];
    protected $hidden = ['portal_token_hash', 'screening_notes'];
    protected $casts = [
        'portal_expires_at' => 'datetime', 'portal_last_accessed_at' => 'datetime',
        'applicant_payload' => 'array', 'eligibility_answers' => 'array', 'submitted_at' => 'datetime',
        'screened_at' => 'datetime', 'offered_at' => 'datetime', 'accepted_at' => 'datetime',
        'rejected_at' => 'datetime', 'withdrawn_at' => 'datetime', 'converted_at' => 'datetime', 'metadata' => 'array',
    ];
    public const STATUSES = ApplicationState::STATUSES;
    protected static function booted(): void
    {
        static::updating(function (self $application): void {
            foreach (['company_id', 'premise_id', 'space_id', 'vacancy_listing_id', 'enquiry_id', 'application_number', 'portal_token_hash'] as $field) {
                if ($application->isDirty($field)) throw new \LogicException("Application identity is immutable: {$field}.");
            }
            if (ApplicationState::isTerminal((string) $application->getOriginal('status'))) throw new \LogicException('Terminal application history is immutable.');
        });
    }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function listing(): BelongsTo { return $this->belongsTo(PremiseVacancyListing::class, 'vacancy_listing_id'); }
    public function enquiry(): BelongsTo { return $this->belongsTo(PremiseVacancyEnquiry::class, 'enquiry_id'); }
    public function applicantPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'applicant_party_role_id'); }
    public function convertedPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'converted_party_role_id'); }
    public function convertedOccupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'converted_occupancy_id'); }
    public function events(): HasMany { return $this->hasMany(PremiseApplicationEvent::class, 'application_id')->orderBy('occurred_at'); }
    public function conversations(): HasMany { return $this->hasMany(PremiseConversation::class, 'application_id'); }
}
