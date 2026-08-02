<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Vacancies\VacancyListingState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseVacancyListing extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_vacancy_listings';
    protected $guarded = ['id'];
    protected $casts = [
        'show_exact_address' => 'boolean',
        'available_from' => 'date',
        'applications_close_at' => 'date',
        'capacity' => 'integer',
        'features' => 'array',
        'eligibility_notes' => 'array',
        'approval_requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'paused_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = VacancyListingState::STATUSES;
    public const CHANNELS = ['website', 'facebook', 'instagram', 'google_business', 'marketplace', 'custom'];

    protected static function booted(): void
    {
        static::updating(function (self $listing): void {
            foreach (['company_id', 'premise_id', 'space_id', 'public_slug'] as $field) {
                if ($listing->isDirty($field)) {
                    throw new \LogicException("Vacancy listing identity is immutable: {$field}.");
                }
            }
            if (VacancyListingState::isTerminal((string) $listing->getOriginal('status'))) {
                throw new \LogicException('Closed and rejected vacancy history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function publications(): HasMany { return $this->hasMany(PremiseVacancyPublication::class, 'vacancy_listing_id')->orderByDesc('id'); }
    public function enquiries(): HasMany { return $this->hasMany(PremiseVacancyEnquiry::class, 'vacancy_listing_id')->orderByDesc('id'); }
    public function applications(): HasMany { return $this->hasMany(PremiseApplication::class, 'vacancy_listing_id')->orderByDesc('id'); }
}
