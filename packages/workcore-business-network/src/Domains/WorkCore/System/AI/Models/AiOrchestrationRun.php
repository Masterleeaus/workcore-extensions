<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiOrchestrationRun extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_orchestration_runs';
    protected $guarded = ['id'];
    protected $casts = [
        'step_count' => 'integer',
        'request_snapshot' => 'array',
        'result_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
