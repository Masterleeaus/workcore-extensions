<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiMessage extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_messages';
    protected $guarded = ['id'];
    protected $casts = ['sequence' => 'integer', 'tool_calls' => 'array', 'metadata' => 'array', 'token_count' => 'integer'];
}
