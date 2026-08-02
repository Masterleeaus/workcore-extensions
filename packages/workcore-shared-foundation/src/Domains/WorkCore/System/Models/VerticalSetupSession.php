<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class VerticalSetupSession extends Model
{
    use BelongsToCompany;
    use HasPublicId;

    protected $table = 'tz_vertical_setup_sessions';

    protected $fillable = [
        'public_id', 'company_id', 'actor_user_id', 'primary_vertical_key',
        'add_on_vertical_keys', 'status', 'answers', 'compiled_plan', 'plan_hash',
        'last_error', 'started_at', 'last_interaction_at', 'applied_at',
    ];

    protected $casts = [
        'add_on_vertical_keys' => 'array', 'answers' => 'array', 'compiled_plan' => 'array',
        'started_at' => 'datetime', 'last_interaction_at' => 'datetime', 'applied_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
