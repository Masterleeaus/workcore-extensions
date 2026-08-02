<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AiAgentVersion extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_ai_agent_versions';
    protected $guarded = ['id'];
    protected $casts = [
        'version' => 'integer',
        'tool_allowlist' => 'array',
        'guardrails' => 'array',
        'published_at' => 'datetime',
    ];
}
