<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyRolePermission extends Model
{
    use BelongsToCompany;

    protected $table = 'tz_company_role_permissions';

    protected $fillable = ['company_id', 'role_id', 'permission', 'access_level'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class, 'role_id');
    }
}
