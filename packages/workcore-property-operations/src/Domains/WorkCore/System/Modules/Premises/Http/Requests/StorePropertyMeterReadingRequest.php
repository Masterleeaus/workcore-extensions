<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use App\Domains\WorkCore\Contracts\WorkCoreActor;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Foundation\Http\FormRequest;

final class StorePropertyMeterReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = app(TenantContextContract::class);
        if (! $tenant->hasTenant()) {
            return false;
        }

        $actor = $this->user();
        $actorId = $actor instanceof WorkCoreActor
            ? $actor->getActorId()
            : (is_object($actor) && method_exists($actor, 'getAuthIdentifier') ? $actor->getAuthIdentifier() : null);

        if (! is_int($actorId) && ! is_string($actorId)) {
            return false;
        }

        return app(PermissionResolverContract::class)->allows(
            $actorId,
            $tenant->companyId(),
            'business.premises.meter_readings.record',
        );
    }

    public function rules(): array
    {
        return [
            'meter_type' => ['required', 'string', 'max:50'],
            'reading_date' => ['required', 'date'],
            'current_reading' => ['required', 'numeric', 'min:0'],
            'previous_reading' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'unit_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
