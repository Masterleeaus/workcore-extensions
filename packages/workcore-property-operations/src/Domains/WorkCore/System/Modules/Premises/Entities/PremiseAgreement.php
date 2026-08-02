<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

use Illuminate\Support\Str;

class PremiseAgreement extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_agreements';


    protected static function booted(): void
    {
        static::creating(static function (self $record): void {
            if (empty($record->public_id)) {
                $record->public_id = (string) Str::ulid();
            }
        });
    }

    protected $guarded = ['id'];

    protected $casts = [
        'offered_at' => 'date',
        'signed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'notice_given_at' => 'date',
        'terminated_at' => 'date',
        'notice_period_days' => 'integer',
        'auto_renew' => 'boolean',
        'metadata' => 'array',
    ];

    public const STATUSES = AgreementState::STATUSES;
    public const INITIAL_STATUSES = AgreementState::INITIAL_STATUSES;

    public const TYPES = [
        'residency_agreement',
        'residential_lease',
        'commercial_lease',
        'storage_agreement',
        'licence_to_occupy',
        'management_agreement',
        'owner_authority',
        'parking_agreement',
        'internal_allocation',
        'short_stay_agreement',
        'service_site_agreement',
        'custom',
    ];

    public const RENEWAL_TYPES = [
        'fixed',
        'periodic',
        'month_to_month',
        'automatic',
        'none',
        'custom',
    ];

    public function premise(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'premise_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(PremiseSpace::class, 'space_id');
    }

    public function occupancy(): BelongsTo
    {
        return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PropertyDocument::class, 'document_id');
    }

    public function previousAgreement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_agreement_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'previous_agreement_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(PremiseAgreementParty::class, 'agreement_id')->orderByDesc('is_primary');
    }

    public function financeLinks(): HasMany
    {
        return $this->hasMany(PremiseAgreementFinanceLink::class, 'agreement_id')->orderBy('link_type');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', ['offered', 'pending_signature', 'active', 'notice_given', 'ending']);
    }

    public function scopeHistory(Builder $query): Builder
    {
        return $query->whereIn('status', AgreementState::TERMINAL_STATUSES);
    }
}
