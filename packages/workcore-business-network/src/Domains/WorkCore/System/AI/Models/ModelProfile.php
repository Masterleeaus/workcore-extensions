<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ModelProfile extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_ai_model_profiles';
    protected $guarded = ['id'];
    protected $casts = [
        'settings' => 'array',
        'priority' => 'integer',
        'max_context_tokens' => 'integer',
        'max_output_tokens' => 'integer',
        'input_cost_per_million' => 'decimal:8',
        'output_cost_per_million' => 'decimal:8',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}
