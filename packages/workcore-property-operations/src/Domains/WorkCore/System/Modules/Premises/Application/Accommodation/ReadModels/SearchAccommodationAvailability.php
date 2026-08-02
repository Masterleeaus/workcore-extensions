<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\ReadModels;

use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\AccommodationAvailabilityService;
use Illuminate\Auth\Access\AuthorizationException;

final class SearchAccommodationAvailability
{
    public function __construct(
        private TenantContextContract $tenant,
        private PermissionResolverContract $permissions,
        private AccommodationAvailabilityService $availability,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $arrivalAt, string $departureAt, int $capacity = 1, ?string $propertyPublicId = null): array
    {
        $companyId = (int) $this->tenant->companyId();
        $this->authorize($companyId);
        $spaces = $this->availability->availableSpaces(
            $companyId,
            $arrivalAt,
            $departureAt,
            $capacity,
            $propertyPublicId,
        );
        return [
            'arrival_at' => $arrivalAt,
            'departure_at' => $departureAt,
            'capacity' => $capacity,
            'property_public_id' => $propertyPublicId,
            'spaces' => $spaces,
            'count' => count($spaces),
        ];
    }

    private function authorize(int $companyId): void
    {
        if (! $this->permissions->allows(
            (int) $this->tenant->userId(),
            $companyId,
            (string) config('workcore.accommodation.permissions.view'),
        )) {
            throw new AuthorizationException('The actor is not permitted to view accommodation availability.');
        }
    }
}
