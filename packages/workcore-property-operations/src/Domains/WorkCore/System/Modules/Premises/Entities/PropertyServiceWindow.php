<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyServiceWindow extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_property_service_windows';
    protected $fillable = ['company_id','user_id','property_id','days','time_from','time_to','notes'];
}
