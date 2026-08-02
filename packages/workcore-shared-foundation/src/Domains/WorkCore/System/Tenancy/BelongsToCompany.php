<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('workcore_company', static function (Builder $query): void {
            $context = app(TenantContextContract::class);
            if ($context->hasTenant()) {
                $query->where($query->qualifyColumn('company_id'), $context->companyId());
            }
        });

        static::creating(function ($model): void {
            $context = app(TenantContextContract::class);
            if (empty($model->company_id) && $context->hasTenant()) {
                $model->company_id = $context->companyId();
            }
            if (config('workcore.require_tenant', true) && empty($model->company_id)) {
                throw new RuntimeException('A company_id is required for tenant-owned WorkCore records.');
            }
        });
    }
    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        $companyId ??= app(TenantContextContract::class)->companyId();
        return $query->where($query->qualifyColumn('company_id'), $companyId);
    }
    public function company(): BelongsTo
    {
        return $this->belongsTo(config('workcore.identity.company_model'), 'company_id');
    }
}
