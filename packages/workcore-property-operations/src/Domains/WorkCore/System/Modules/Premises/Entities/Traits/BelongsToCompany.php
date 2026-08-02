<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Traits;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (property_exists($model, 'company_id') && empty($model->company_id) && function_exists('company') && company()) {
                $model->company_id = company()->id;
            }
        });
    }
}
