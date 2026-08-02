<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAccessItem extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_access_items';
    protected $guarded = ['id'];

    protected $hidden = ['secret_value'];

    protected $casts = [
        'secret_value' => 'encrypted',
        'issued_at' => 'datetime',
        'expected_return_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const TYPES = [
        'physical_key', 'master_key', 'access_card', 'pin_code', 'alarm_code',
        'gate_code', 'intercom_code', 'gate_remote', 'mobile_credential',
        'lockbox', 'locker_key', 'parking_remote', 'other',
    ];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function currentPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'current_party_role_id'); }
    public function events(): HasMany { return $this->hasMany(PremiseAccessEvent::class, 'access_item_id')->orderByDesc('occurred_at'); }

    public function maskedSecret(): ?string
    {
        if (!$this->secret_value) {
            return null;
        }
        $value = (string) $this->secret_value;
        $tail = mb_substr($value, -4);
        return str_repeat('•', max(4, min(8, mb_strlen($value) - 4))) . $tail;
    }
}
