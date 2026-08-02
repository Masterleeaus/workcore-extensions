<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseConversation extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_conversations';
    protected $guarded = ['id'];
    protected $casts = ['last_message_at' => 'datetime', 'closed_at' => 'datetime', 'metadata' => 'array'];
    protected static function booted(): void
    {
        static::updating(function (self $conversation): void {
            foreach (['company_id', 'premise_id', 'portal_grant_id', 'application_id', 'party_role_id', 'occupancy_id', 'incident_id'] as $field) {
                if ($conversation->isDirty($field)) throw new \LogicException("Conversation identity is immutable: {$field}.");
            }
            if ($conversation->getOriginal('status') === 'closed') throw new \LogicException('Closed conversation history is immutable.');
        });
    }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function portalGrant(): BelongsTo { return $this->belongsTo(PremisePortalGrant::class, 'portal_grant_id'); }
    public function application(): BelongsTo { return $this->belongsTo(PremiseApplication::class, 'application_id'); }
    public function messages(): HasMany { return $this->hasMany(PremiseMessage::class, 'conversation_id')->orderBy('sent_at'); }
}
