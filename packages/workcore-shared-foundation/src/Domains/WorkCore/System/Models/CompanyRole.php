<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CompanyRole extends Model
{
    use BelongsToCompany;

    protected $table = 'tz_company_roles';

    protected $fillable = ['company_id', 'name', 'key', 'is_system', 'priority'];

    protected $casts = ['is_system' => 'boolean', 'priority' => 'integer'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(CompanyRolePermission::class, 'role_id');
    }
}
