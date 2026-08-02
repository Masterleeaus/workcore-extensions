<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AiConversation extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_ai_conversations';
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'last_message_at' => 'datetime'];
}
