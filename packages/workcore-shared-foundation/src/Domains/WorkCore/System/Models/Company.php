<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Models;

use App\Domains\WorkCore\System\Support\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Company extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $table = 'tz_companies';

    protected $fillable = [
        'public_id',
        'name',
        'slug',
        'status',
        'timezone',
        'currency_code',
        'country_code',
        'abn',
        'gst_registered',
        'owner_user_id',
        'settings',
    ];

    protected $casts = [
        'gst_registered' => 'boolean',
        'settings' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo((string) config('workcore.identity.user_model'), 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMember::class, 'company_id');
    }


    public function verticals(): HasMany
    {
        return $this->hasMany(CompanyVertical::class, 'company_id');
    }

    public function businessLines(): HasMany
    {
        return $this->hasMany(BusinessLine::class, 'company_id');
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(CompanyFeature::class, 'company_id');
    }

    public function terminologyOverrides(): HasMany
    {
        return $this->hasMany(CompanyTerminologyOverride::class, 'company_id');
    }

    public function locales(): HasMany
    {
        return $this->hasMany(CompanyLocale::class, 'company_id');
    }

    public function companySettings(): HasMany
    {
        return $this->hasMany(CompanySetting::class, 'company_id');
    }

    public function documentTemplates(): HasMany
    {
        return $this->hasMany(CompanyDocumentTemplate::class, 'company_id');
    }

    public function verticalSetupSessions(): HasMany
    {
        return $this->hasMany(VerticalSetupSession::class, 'company_id');
    }
}
