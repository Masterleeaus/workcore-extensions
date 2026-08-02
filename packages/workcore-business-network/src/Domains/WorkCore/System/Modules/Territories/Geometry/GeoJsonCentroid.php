<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Geometry;

use InvalidArgumentException;

final class GeoJsonCentroid
{
    /** @param array<string,mixed> $polygon @return array{latitude:float,longitude:float} */
    public function centroid(array $polygon): array
    {
        $ring = $polygon['coordinates'][0] ?? null;
        if (! is_array($ring) || count($ring) < 4) {
            throw new InvalidArgumentException('A valid exterior polygon ring is required.');
        }

        $areaTwice = 0.0;
        $cx = 0.0;
        $cy = 0.0;
        for ($i = 0, $last = count($ring) - 1; $i < $last; $i++) {
            [$x1, $y1] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$x2, $y2] = [(float) $ring[$i + 1][0], (float) $ring[$i + 1][1]];
            $cross = ($x1 * $y2) - ($x2 * $y1);
            $areaTwice += $cross;
            $cx += ($x1 + $x2) * $cross;
            $cy += ($y1 + $y2) * $cross;
        }

        if (abs($areaTwice) < 1.0e-12) {
            $points = array_slice($ring, 0, -1);
            $count = count($points);
            return [
                'latitude' => array_sum(array_column($points, 1)) / $count,
                'longitude' => array_sum(array_column($points, 0)) / $count,
            ];
        }

        return [
            'latitude' => $cy / (3.0 * $areaTwice),
            'longitude' => $cx / (3.0 * $areaTwice),
        ];
    }

    /** @param array<string,mixed> $polygon @return array{south:float,west:float,north:float,east:float} */
    public function bounds(array $polygon): array
    {
        $ring = $polygon['coordinates'][0] ?? null;
        if (! is_array($ring) || $ring === []) {
            throw new InvalidArgumentException('A valid exterior polygon ring is required.');
        }
        $longitudes = array_map(static fn (array $point): float => (float) $point[0], $ring);
        $latitudes = array_map(static fn (array $point): float => (float) $point[1], $ring);
        return ['south' => min($latitudes), 'west' => min($longitudes), 'north' => max($latitudes), 'east' => max($longitudes)];
    }
}
