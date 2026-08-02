<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyMemberPermission extends Model
{
    use BelongsToCompany;

    protected $table = 'tz_company_member_permissions';

    protected $fillable = ['company_id', 'membership_id', 'permission', 'access_level'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CompanyMember::class, 'membership_id');
    }
}
