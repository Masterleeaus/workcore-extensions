<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyTag extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_property_tags';
    protected $fillable = ['company_id','user_id','property_id','tag'];
}
