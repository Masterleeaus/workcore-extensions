<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CompanyMember extends Model
{
    use BelongsToCompany;

    protected $table = 'tz_company_memberships';

    protected $fillable = ['company_id', 'user_id', 'role_id', 'role_key', 'status', 'is_owner', 'joined_at'];

    protected $casts = ['is_owner' => 'boolean', 'joined_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo((string) config('workcore.identity.user_model'), 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CompanyRole::class, 'role_id');
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(CompanyMemberPermission::class, 'membership_id');
    }
}
