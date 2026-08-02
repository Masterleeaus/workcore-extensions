<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiToolRun extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_tool_runs';
    protected $guarded = ['id'];
    protected $casts = [
        'input_snapshot' => 'array',
        'output_snapshot' => 'array',
        'event_ids' => 'array',
        'latency_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
