<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyPhoto extends Model
{
    use BelongsToCompany;
    public const FILE_PATH = 'property-management/photos';

    protected $table = 'pm_property_photos';
    protected $fillable = ['company_id','user_id','property_id','property_job_id','path','file_path','caption','uploaded_at'];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
