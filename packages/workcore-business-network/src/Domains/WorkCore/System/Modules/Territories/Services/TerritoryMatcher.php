<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Services;

use App\Domains\WorkCore\System\Modules\Territories\Geometry\PointInPolygon;
use JsonException;

final class TerritoryMatcher
{
    public const EXACT_POSTCODE_SCORE = 100;
    public const POLYGON_SCORE = 90;
    public const SUBURB_SCORE = 80;
    public const POSTCODE_PREFIX_BASE_SCORE = 70;
    public const RADIUS_BASE_SCORE = 60;

    public function __construct(private PointInPolygon $pointInPolygon) {}

    /** @param array<string,mixed> $territory */
    public function matches(array $territory, ?string $postcode = null, ?string $suburb = null, ?float $latitude = null, ?float $longitude = null): bool
    {
        return $this->score($territory, $postcode, $suburb, $latitude, $longitude) > 0;
    }

    /** @param array<string,mixed> $territory */
    public function score(array $territory, ?string $postcode = null, ?string $suburb = null, ?float $latitude = null, ?float $longitude = null): int
    {
        $score = 0;
        $postcode = strtoupper(trim((string) $postcode));
        $suburb = $this->lower(trim((string) $suburb));

        foreach ($this->list($territory['postcode_patterns'] ?? []) as $pattern) {
            $pattern = strtoupper(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }
            if ($postcode !== '' && $pattern === $postcode) {
                $score = max($score, self::EXACT_POSTCODE_SCORE);
            }
            if ($postcode !== '' && str_ends_with($pattern, '*')) {
                $prefix = rtrim($pattern, '*');
                if ($prefix !== '' && str_starts_with($postcode, $prefix)) {
                    $score = max($score, self::POSTCODE_PREFIX_BASE_SCORE + min(9, strlen($prefix)));
                }
            }
        }

        foreach ($this->list($territory['suburbs'] ?? []) as $candidate) {
            if ($suburb !== '' && $this->lower(trim((string) $candidate)) === $suburb) {
                $score = max($score, self::SUBURB_SCORE);
            }
        }

        if ($latitude !== null && $longitude !== null) {
            $polygon = $this->polygon($territory['polygon_geojson'] ?? null);
            if ($polygon !== null && $this->pointInPolygon->contains($latitude, $longitude, $polygon)) {
                $score = max($score, self::POLYGON_SCORE);
            }

            if ($this->hasRadius($territory)) {
                $distance = $this->distanceKm(
                    $latitude,
                    $longitude,
                    (float) $territory['centre_latitude'],
                    (float) $territory['centre_longitude'],
                );
                if ($distance <= (float) $territory['radius_km']) {
                    $score = max($score, max(1, self::RADIUS_BASE_SCORE - (int) floor($distance)));
                }
            }
        }

        return $score;
    }

    /** @return array<string,mixed>|null */
    private function polygon(mixed $value): ?array
    {
        if (is_array($value)) {
            return ($value['type'] ?? null) === 'Polygon' ? $value : null;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        return is_array($decoded) && ($decoded['type'] ?? null) === 'Polygon' ? $decoded : null;
    }

    /** @param array<string,mixed> $territory */
    private function hasRadius(array $territory): bool
    {
        return isset($territory['centre_latitude'], $territory['centre_longitude'], $territory['radius_km'])
            && is_numeric($territory['centre_latitude'])
            && is_numeric($territory['centre_longitude'])
            && is_numeric($territory['radius_km'])
            && (float) $territory['radius_km'] > 0.0;
    }

    /** @return array<int,mixed> */
    private function list(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values($decoded);
            }
            return array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $value) ?: [])));
        }
        return [];
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }
}
