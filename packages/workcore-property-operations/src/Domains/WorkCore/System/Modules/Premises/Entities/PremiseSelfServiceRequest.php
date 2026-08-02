<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Requests\ServiceRequestState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseSelfServiceRequest extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_self_service_requests';
    protected $guarded = ['id'];
    protected $casts = ['workcore_linked_at' => 'datetime', 'submitted_at' => 'datetime', 'triaged_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'metadata' => 'array'];
    public const STATUSES = ServiceRequestState::STATUSES;
    public const TYPES = ['maintenance', 'access', 'document', 'permission', 'contact_update', 'complaint', 'general'];
    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            foreach (['company_id', 'premise_id', 'space_id', 'portal_grant_id', 'party_role_id', 'occupancy_id', 'request_number'] as $field) {
                if ($request->isDirty($field)) throw new \LogicException("Self-service request identity is immutable: {$field}.");
            }
            if (ServiceRequestState::isTerminal((string) $request->getOriginal('status'))) throw new \LogicException('Terminal self-service request history is immutable.');
        });
    }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function portalGrant(): BelongsTo { return $this->belongsTo(PremisePortalGrant::class, 'portal_grant_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
}
