<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanySetting extends Model
{
    use BelongsToCompany;
    protected $table = 'tz_company_settings';

    protected $fillable = ['company_id', 'setting_key', 'setting_group', 'value', 'source', 'is_locked'];

    protected $casts = ['value' => 'array', 'is_locked' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
