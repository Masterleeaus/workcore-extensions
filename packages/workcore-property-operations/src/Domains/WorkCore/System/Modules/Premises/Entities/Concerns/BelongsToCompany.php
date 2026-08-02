<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Canonical tenant boundary for managed-premises records.
 *
 * Every model using this concern must map to a table with a company_id column.
 * Host user IDs are audit metadata only and never form the tenant boundary.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('workcore_company', static function (Builder $builder): void {
            $context = app(TenantContextContract::class);
            if ($context->hasTenant()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('company_id'),
                    $context->companyId(),
                );
            }
        });

        static::creating(static function ($model): void {
            $context = app(TenantContextContract::class);
            if (empty($model->company_id) && $context->hasTenant()) {
                $model->company_id = $context->companyId();
            }

            if ((bool) config('workcore.require_tenant', true) && empty($model->company_id)) {
                throw new RuntimeException('A company_id is required for tenant-owned managed-premises records.');
            }
        });
    }
}
