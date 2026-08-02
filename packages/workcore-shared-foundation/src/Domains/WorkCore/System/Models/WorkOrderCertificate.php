<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WorkOrderCertificate extends Model
{
    use BelongsToCompany;
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_work_order_certificates';

    protected $fillable = [
        'public_id', 'company_id', 'work_order_id', 'business_line_id', 'worker_id',
        'trade_licence_id', 'vertical_key', 'certificate_type', 'certificate_number',
        'status', 'issued_at', 'test_results', 'declaration', 'document_path', 'notes',
        'voided_at', 'void_reason', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime', 'voided_at' => 'datetime',
        'test_results' => 'array', 'declaration' => 'array',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class, 'company_id'); }
    public function businessLine(): BelongsTo { return $this->belongsTo(BusinessLine::class, 'business_line_id'); }
    public function tradeLicence(): BelongsTo { return $this->belongsTo(TradeLicence::class, 'trade_licence_id'); }
}
