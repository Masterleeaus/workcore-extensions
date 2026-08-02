<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyAsset extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_property_assets';
    protected $fillable = ['company_id','user_id','property_id','label','category','serial','location','notes'];
}
