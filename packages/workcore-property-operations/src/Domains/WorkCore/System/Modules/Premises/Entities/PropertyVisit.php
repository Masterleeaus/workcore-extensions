<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyVisit extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'pm_property_visits';

    protected $fillable = [
        'company_id','property_id','service_plan_id','visit_type','scheduled_for','assigned_to',
        'status','notes','completed_at'
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function property(){ return $this->belongsTo(Property::class, 'property_id'); }
    public function plan(){ return $this->belongsTo(PropertyServicePlan::class, 'service_plan_id'); }
}
