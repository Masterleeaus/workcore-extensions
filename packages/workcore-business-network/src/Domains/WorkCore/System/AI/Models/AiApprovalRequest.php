<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;

final class AiApprovalRequest extends Model
{
    use HasPublicId;

    protected $table = 'tz_ai_approval_requests';
    protected $guarded = ['id'];
    protected $casts = ['tool_input' => 'array', 'expires_at' => 'datetime', 'decided_at' => 'datetime', 'consumed_at' => 'datetime'];
}
