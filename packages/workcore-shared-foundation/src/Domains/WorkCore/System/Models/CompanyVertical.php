<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CompanyVertical extends Model
{
    use BelongsToCompany;
    use HasPublicId;

    protected $table = 'tz_company_verticals';

    protected $fillable = [
        'public_id', 'company_id', 'vertical_key', 'pack_version', 'is_primary',
        'status', 'settings', 'activated_by_user_id', 'activated_at', 'deactivated_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'settings' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function businessLines(): HasMany
    {
        return $this->hasMany(BusinessLine::class, 'company_vertical_id');
    }
}
