<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyHazard extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_property_hazards';
    protected $fillable = ['company_id','user_id','property_id','hazard','risk_level','controls'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
