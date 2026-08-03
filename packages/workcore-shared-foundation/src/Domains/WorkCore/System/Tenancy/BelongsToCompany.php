<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Contracts\PrivilegedTenantAccessContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('workcore_company', static function (Builder $query): void {
            $context = app(TenantContextContract::class);
            if ($context->hasTenant()) {
                $query->where($query->qualifyColumn('company_id'), $context->companyId());

                return;
            }

            $privileged = app(PrivilegedTenantAccessContract::class);
            if ($privileged->isActive()) {
                return;
            }

            if ((bool) config('workcore.require_tenant', true)) {
                throw new MissingTenantContextException(
                    'Tenant-owned WorkCore records cannot be queried without an active company context.',
                );
            }
        });

        static::creating(static function ($model): void {
            $context = app(TenantContextContract::class);
            if (empty($model->company_id) && $context->hasTenant()) {
                $model->company_id = $context->companyId();
            }
            if ((bool) config('workcore.require_tenant', true) && empty($model->company_id)) {
                throw new MissingTenantContextException(
                    'A company_id is required for tenant-owned WorkCore records.',
                );
            }
        });
    }

    public static function queryForExplicitCompany(int $companyId): Builder
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('An explicit positive company ID is required.');
        }

        $model = new static();

        return static::withoutGlobalScope('workcore_company')
            ->where($model->qualifyColumn('company_id'), $companyId);
    }

    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        if ($companyId === null) {
            $context = app(TenantContextContract::class);
            if (! $context->hasTenant()) {
                throw new MissingTenantContextException(
                    'An explicit company ID or active WorkCore tenant is required.',
                );
            }
            $companyId = $context->companyId();
        }

        return $query->where($query->qualifyColumn('company_id'), $companyId);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(config('workcore.identity.company_model'), 'company_id');
    }
}
