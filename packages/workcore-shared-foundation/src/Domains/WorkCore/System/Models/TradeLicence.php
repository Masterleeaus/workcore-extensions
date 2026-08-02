<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TradeLicence extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_trade_licences';

    protected $fillable = [
        'public_id', 'company_id', 'worker_id', 'business_line_id', 'vertical_key',
        'licence_type', 'licence_class', 'licence_number', 'jurisdiction', 'issuer',
        'status', 'issued_on', 'expires_on', 'verified_at', 'verified_by_user_id',
        'conditions', 'document_path', 'notes',
    ];

    protected $casts = [
        'issued_on' => 'date', 'expires_on' => 'date', 'verified_at' => 'datetime',
        'conditions' => 'array',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function businessLine(): BelongsTo { return $this->belongsTo(BusinessLine::class, 'business_line_id'); }
    public function certificates(): HasMany { return $this->hasMany(WorkOrderCertificate::class, 'trade_licence_id'); }
}
