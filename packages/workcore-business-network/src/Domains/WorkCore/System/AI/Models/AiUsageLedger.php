<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiUsageLedger extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_usage_ledger';
    protected $guarded = ['id'];
    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost' => 'decimal:8',
        'occurred_at' => 'datetime',
    ];
}
