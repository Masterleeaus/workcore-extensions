<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseWorkPermitApproval extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_work_permit_approvals';
    protected $guarded = ['id'];
    protected $casts = [
        'evidence_document_ids' => 'array',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Work-permit approval history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Work-permit approval history cannot be deleted.'));
    }

    public function workPermit(): BelongsTo { return $this->belongsTo(PremiseWorkPermit::class, 'work_permit_id'); }
}
