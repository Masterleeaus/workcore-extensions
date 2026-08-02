<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Http\Requests;

use App\Domains\WorkCore\System\Modules\Territories\Geometry\PolygonGeoJson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class StoreTerritoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_public_id' => ['required', 'string', 'size:26'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'territory_type' => ['nullable', 'in:postcode,suburb,radius,polygon,mixed'],
            'postcode_patterns' => ['nullable', 'array'],
            'postcode_patterns.*' => ['string', 'max:20'],
            'suburbs' => ['nullable', 'array'],
            'suburbs.*' => ['string', 'max:150'],
            'state' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'centre_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'centre_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.01', 'max:5000'],
            'polygon_geojson' => ['nullable', 'string', 'max:2000000'],
            'status' => ['nullable', 'in:active,inactive,archived'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('territory_type', 'postcode');
            $polygon = $this->input('polygon_geojson');
            $latitude = $this->input('centre_latitude');
            $longitude = $this->input('centre_longitude');
            $radius = $this->input('radius_km');

            if ($type === 'polygon' && (! is_string($polygon) || trim($polygon) === '')) {
                $validator->errors()->add('polygon_geojson', 'Polygon GeoJSON is required for polygon territories.');
            }
            if ($type === 'radius' && ($latitude === null || $longitude === null || $radius === null)) {
                $validator->errors()->add('radius_km', 'Centre latitude, centre longitude and radius are required for radius territories.');
            }
            if ($type === 'mixed' && ! $this->hasAnyMatcherInput()) {
                $validator->errors()->add('territory_type', 'Mixed territories require at least one postcode, suburb, radius or polygon matcher.');
            }
            if (is_string($polygon) && trim($polygon) !== '') {
                try {
                    (new PolygonGeoJson())->normalise($polygon);
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('polygon_geojson', $exception->getMessage());
                }
            }
        });
    }

    private function hasAnyMatcherInput(): bool
    {
        return (array) $this->input('postcode_patterns', []) !== []
            || (array) $this->input('suburbs', []) !== []
            || ($this->input('centre_latitude') !== null && $this->input('centre_longitude') !== null && $this->input('radius_km') !== null)
            || trim((string) $this->input('polygon_geojson', '')) !== '';
    }
}
