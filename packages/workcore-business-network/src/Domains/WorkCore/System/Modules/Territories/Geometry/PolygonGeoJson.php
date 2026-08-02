<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Geometry;

use InvalidArgumentException;
use JsonException;

final class PolygonGeoJson
{
    /** @param string|array<string,mixed> $input */
    public function normalise(string|array $input): string
    {
        try {
            $value = is_string($input)
                ? json_decode($input, true, 512, JSON_THROW_ON_ERROR)
                : $input;
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Territory geometry must be valid GeoJSON.', previous: $exception);
        }

        if (($value['type'] ?? null) !== 'Polygon') {
            throw new InvalidArgumentException('Territory geometry must be a GeoJSON Polygon.');
        }

        $rings = $value['coordinates'] ?? null;
        if (! is_array($rings) || $rings === []) {
            throw new InvalidArgumentException('Polygon coordinates are required.');
        }

        $normalised = [];
        foreach (array_values($rings) as $ringIndex => $ring) {
            if (! is_array($ring)) {
                throw new InvalidArgumentException("Polygon ring {$ringIndex} must be an array.");
            }

            $points = [];
            foreach (array_values($ring) as $point) {
                $points[] = $this->normalisePoint($point);
            }

            if ($points !== [] && $points[0] === $points[array_key_last($points)]) {
                array_pop($points);
            }

            $distinct = array_unique(array_map(
                static fn (array $point): string => sprintf('%.12F,%.12F', $point[0], $point[1]),
                $points,
            ));
            if (count($distinct) < 3) {
                throw new InvalidArgumentException('A polygon ring requires at least three distinct points.');
            }

            $points[] = $points[0];
            $normalised[] = $points;
        }

        return json_encode(
            ['type' => 'Polygon', 'coordinates' => $normalised],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    /** @return array{0:float,1:float} */
    private function normalisePoint(mixed $point): array
    {
        if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
            throw new InvalidArgumentException('Each polygon point must contain numeric longitude and latitude.');
        }

        $longitude = (float) $point[0];
        $latitude = (float) $point[1];
        if (! is_finite($longitude) || ! is_finite($latitude)) {
            throw new InvalidArgumentException('Polygon coordinates must be finite numbers.');
        }
        if ($longitude < -180.0 || $longitude > 180.0 || $latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('Polygon coordinate is outside longitude/latitude bounds.');
        }

        return [$longitude, $latitude];
    }
}
