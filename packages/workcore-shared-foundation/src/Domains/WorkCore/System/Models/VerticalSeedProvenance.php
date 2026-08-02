<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VerticalSeedProvenance extends Model
{
    use BelongsToCompany;
    protected $table = 'tz_vertical_seed_provenance';

    protected $fillable = [
        'company_id', 'vertical_key', 'seed_key', 'record_type', 'record_id',
        'installed_version', 'content_hash', 'is_customer_modified', 'metadata',
    ];

    protected $casts = ['is_customer_modified' => 'boolean', 'metadata' => 'array'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
