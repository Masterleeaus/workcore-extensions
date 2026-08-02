<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementFinanceLinkType;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAgreementFinanceLink extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_agreement_finance_links';
    protected $guarded = ['id'];

    protected $casts = [
        'linked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const LINK_TYPES = AgreementFinanceLinkType::TYPES;
    public const PROVIDERS = ['titan_money', 'external'];
    public const STATUSES = ['linked', 'pending', 'inactive', 'failed'];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PremiseAgreement::class, 'agreement_id');
    }
}
