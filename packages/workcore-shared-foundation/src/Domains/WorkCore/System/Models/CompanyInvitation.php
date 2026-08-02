<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyInvitation extends Model
{
    use BelongsToCompany;

    use HasPublicId;

    protected $table = 'tz_company_invitations';

    protected $fillable = [
        'public_id', 'company_id', 'role_id', 'email', 'role_key', 'token_hash', 'status',
        'invited_by_user_id', 'accepted_by_user_id', 'expires_at', 'accepted_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function role(): BelongsTo { return $this->belongsTo(CompanyRole::class, 'role_id'); }
}
