<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BusinessLine extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_business_lines';

    protected $fillable = [
        'public_id', 'company_id', 'company_vertical_id', 'key', 'name',
        'is_default', 'status', 'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function vertical(): BelongsTo
    {
        return $this->belongsTo(CompanyVertical::class, 'company_vertical_id');
    }

    public function workerAssignments(): HasMany
    {
        return $this->hasMany(WorkerBusinessLine::class, 'business_line_id');
    }

    public function tradeLicences(): HasMany
    {
        return $this->hasMany(TradeLicence::class, 'business_line_id');
    }

    public function workOrderCertificates(): HasMany
    {
        return $this->hasMany(WorkOrderCertificate::class, 'business_line_id');
    }
}
