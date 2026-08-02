<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Communications\MessageVisibility;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseMessage extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_messages';
    protected $guarded = ['id'];
    protected $casts = ['sent_at' => 'datetime', 'read_at' => 'datetime', 'metadata' => 'array'];
    public const VISIBILITIES = MessageVisibility::VALUES;
    protected static function booted(): void
    {
        static::updating(function (self $message): void {
            $allowed = ['read_at'];
            foreach (array_keys($message->getDirty()) as $field) if (!in_array($field, $allowed, true)) throw new \LogicException('Message history is immutable.');
        });
        static::deleting(fn () => throw new \LogicException('Message history cannot be deleted.'));
    }
    public function conversation(): BelongsTo { return $this->belongsTo(PremiseConversation::class, 'conversation_id'); }
}
