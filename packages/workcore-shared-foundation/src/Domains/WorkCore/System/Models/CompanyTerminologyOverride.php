<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyTerminologyOverride extends Model
{
    use BelongsToCompany;
    protected $table = 'tz_company_terminology_overrides';

    protected $fillable = ['company_id', 'scope_type', 'scope_id', 'locale', 'term_key', 'term_value'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
