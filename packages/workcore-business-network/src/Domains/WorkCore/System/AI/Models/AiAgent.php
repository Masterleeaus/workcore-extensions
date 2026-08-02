<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AiAgent extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_ai_agents';
    protected $guarded = ['id'];
    protected $casts = [
        'settings' => 'array',
    ];
}
