<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremisePortfolioMembership extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_portfolio_memberships';
    protected $guarded = ['id'];
    protected $casts = ['valid_from' => 'date', 'valid_until' => 'date', 'metadata' => 'array'];

    public const TYPES = ['primary', 'secondary', 'reporting', 'investment', 'custom'];

    protected static function booted(): void
    {
        static::updating(function (self $membership): void {
            foreach (['company_id', 'portfolio_id', 'premise_id', 'membership_type', 'valid_from'] as $field) {
                if ($membership->isDirty($field)) throw new \LogicException("Portfolio membership identity is immutable: {$field}.");
            }
            if ($membership->getOriginal('valid_until') && $membership->isDirty('valid_until')) {
                throw new \LogicException('Ended portfolio membership history is immutable.');
            }
        });
    }

    public function portfolio(): BelongsTo { return $this->belongsTo(PremisePortfolio::class, 'portfolio_id'); }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
}
