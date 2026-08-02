<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ProviderProfile extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_ai_provider_profiles';
    protected $guarded = ['id'];
    protected $hidden = ['secret_reference'];
    protected $casts = [
        'settings' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];
}
