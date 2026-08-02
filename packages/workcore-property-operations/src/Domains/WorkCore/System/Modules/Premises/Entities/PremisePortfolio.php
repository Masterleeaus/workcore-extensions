<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremisePortfolio extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_portfolios';
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array'];

    public const STATUSES = ['active', 'archived'];

    protected static function booted(): void
    {
        static::updating(function (self $portfolio): void {
            if ($portfolio->isDirty('company_id')) {
                throw new \LogicException('Portfolio company identity is immutable.');
            }
            if ($portfolio->getOriginal('status') === 'archived' && $portfolio->isDirty('status')) {
                throw new \LogicException('Archived portfolio history cannot be reactivated.');
            }
        });
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_portfolio_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_portfolio_id')->orderBy('name'); }
    public function memberships(): HasMany { return $this->hasMany(PremisePortfolioMembership::class, 'portfolio_id'); }
    public function approvalRequests(): HasMany { return $this->hasMany(PremiseApprovalRequest::class, 'portfolio_id'); }
}
