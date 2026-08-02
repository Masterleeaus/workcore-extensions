<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Vacancies\VacancyPublicationState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseVacancyPublication extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_vacancy_publications';
    protected $guarded = ['id'];
    protected $casts = [
        'payload_snapshot' => 'array',
        'requested_at' => 'datetime',
        'published_at' => 'datetime',
        'failed_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $publication): void {
            foreach (['company_id', 'vacancy_listing_id', 'channel', 'payload_snapshot'] as $field) {
                if ($publication->isDirty($field)) {
                    throw new \LogicException("Publication identity and payload snapshots are immutable: {$field}.");
                }
            }
            if (VacancyPublicationState::isTerminal((string) $publication->getOriginal('status')) && $publication->getOriginal('status') !== 'published') {
                throw new \LogicException('Failed and withdrawn publication history is immutable.');
            }
        });
    }

    public function listing(): BelongsTo { return $this->belongsTo(PremiseVacancyListing::class, 'vacancy_listing_id'); }
}
