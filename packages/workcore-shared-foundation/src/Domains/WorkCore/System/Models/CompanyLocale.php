<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyLocale extends Model
{
    use BelongsToCompany;
    protected $table = 'tz_company_locales';

    protected $fillable = [
        'company_id', 'locale', 'display_name', 'direction', 'is_default',
        'is_active', 'source', 'settings',
    ];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean', 'settings' => 'array'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
