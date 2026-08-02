<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PropertyDocument extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'pm_property_documents';

    protected $fillable = [
        'company_id','user_id','property_id','property_job_id',
        'name','doc_type','stored_path','mime','size_bytes',
        'uploaded_by','notes'
    ];

    public function property(){ return $this->belongsTo(Property::class, 'property_id'); }
    public function agreements(){ return $this->hasMany(PremiseAgreement::class, 'document_id'); }
}
