<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VerticalInstallation extends Model
{
    use BelongsToCompany;
    use HasPublicId;

    protected $table = 'tz_vertical_installations';

    protected $fillable = [
        'public_id', 'company_id', 'vertical_key', 'installed_version', 'status',
        'manifest_hash', 'installed_by_user_id', 'installed_at', 'upgraded_at',
        'deactivated_at', 'metadata',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'upgraded_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
