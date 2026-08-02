<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyFeature extends Model
{
    use BelongsToCompany;
    protected $table = 'tz_company_features';

    protected $fillable = ['company_id', 'scope_type', 'scope_id', 'feature_key', 'enabled', 'source', 'settings'];

    protected $casts = ['enabled' => 'boolean', 'settings' => 'array'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
