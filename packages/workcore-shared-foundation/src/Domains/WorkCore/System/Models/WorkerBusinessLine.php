<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkerBusinessLine extends Model
{
    use BelongsToCompany;
    use HasPublicId;

    protected $table = 'tz_worker_business_lines';

    protected $fillable = [
        'public_id', 'company_id', 'worker_id', 'business_line_id', 'role',
        'is_primary', 'active', 'assigned_at', 'assigned_by_user_id', 'settings',
    ];

    protected $casts = [
        'is_primary' => 'boolean', 'active' => 'boolean',
        'assigned_at' => 'datetime', 'settings' => 'array',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function businessLine(): BelongsTo { return $this->belongsTo(BusinessLine::class, 'business_line_id'); }
}
