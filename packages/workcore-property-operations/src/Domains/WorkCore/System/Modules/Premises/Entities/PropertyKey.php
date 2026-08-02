<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyKey extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_property_keys';
    protected $hidden = ['code'];
    protected $fillable = ['company_id','user_id','property_id','type','location','code','notes'];

    public function maskedCode(): ?string
    {
        if (!$this->code) {
            return null;
        }
        $value = (string) $this->code;
        return str_repeat('•', max(4, min(8, strlen($value) - 4))) . substr($value, -4);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
