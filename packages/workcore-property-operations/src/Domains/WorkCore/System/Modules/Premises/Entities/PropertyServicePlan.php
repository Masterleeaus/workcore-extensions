<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyServicePlan extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'pm_property_service_plans';

    protected $fillable = [
        'company_id','property_id','name','service_type','rrule','starts_on','ends_on',
        'preferred_days','preferred_times','notes','is_active'
    ];

    protected $casts = [
        'preferred_days' => 'array',
        'preferred_times' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
