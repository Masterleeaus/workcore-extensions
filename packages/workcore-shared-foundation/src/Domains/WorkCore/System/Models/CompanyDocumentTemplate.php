<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CompanyDocumentTemplate extends Model
{
    use BelongsToCompany;
    use HasPublicId;

    protected $table = 'tz_company_document_templates';

    protected $fillable = [
        'public_id', 'company_id', 'business_line_id', 'vertical_key', 'document_key',
        'document_type', 'title', 'locale', 'content', 'metadata', 'source_version', 'is_active',
    ];

    protected $casts = ['metadata' => 'array', 'is_active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class, 'business_line_id');
    }
}
