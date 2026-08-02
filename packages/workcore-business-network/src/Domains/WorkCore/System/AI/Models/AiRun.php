<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiRun extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_runs';
    protected $guarded = ['id'];
    protected $casts = [
        'request_snapshot' => 'array',
        'response_snapshot' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost' => 'decimal:8',
        'latency_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
