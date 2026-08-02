<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiOrchestrationStep extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_orchestration_steps';
    protected $guarded = ['id'];
    protected $casts = [
        'sequence' => 'integer',
        'input_snapshot' => 'array',
        'output_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
