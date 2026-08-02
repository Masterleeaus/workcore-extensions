<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Approvals\ApprovalRequestState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseApprovalRequest extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_approval_requests';
    protected $guarded = ['id'];
    protected $hidden = ['payload_snapshot'];
    protected $casts = [
        'required_decisions' => 'integer', 'payload_snapshot' => 'array',
        'requested_at' => 'datetime', 'due_at' => 'datetime', 'decided_at' => 'datetime',
        'implemented_at' => 'datetime', 'withdrawn_at' => 'datetime', 'metadata' => 'array',
    ];

    public const STATUSES = ApprovalRequestState::STATUSES;
    public const TYPES = [
        'maintenance_quote', 'capital_work', 'lease_offer', 'agreement_renewal',
        'applicant_selection', 'compliance_work', 'access_exception',
        'vacancy_publication', 'finance_reference', 'other',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            foreach (['company_id', 'premise_id', 'portfolio_id', 'requested_party_role_id', 'request_type', 'linked_module', 'linked_id'] as $field) {
                if ($request->isDirty($field)) throw new \LogicException("Approval request identity is immutable: {$field}.");
            }
            if (ApprovalRequestState::isTerminal((string) $request->getOriginal('status'))) {
                throw new \LogicException('Terminal approval request history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function portfolio(): BelongsTo { return $this->belongsTo(PremisePortfolio::class, 'portfolio_id'); }
    public function requestedPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'requested_party_role_id'); }
    public function decisions(): HasMany { return $this->hasMany(PremiseApprovalDecision::class, 'approval_request_id')->orderBy('decided_at'); }
}
