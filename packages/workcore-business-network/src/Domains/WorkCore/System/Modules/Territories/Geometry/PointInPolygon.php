<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Geometry;

final class PointInPolygon
{
    /** @param array<string,mixed> $polygon */
    public function contains(float $latitude, float $longitude, array $polygon): bool
    {
        $rings = $polygon['coordinates'] ?? [];
        if (! is_array($rings) || $rings === [] || ! $this->insideRing($longitude, $latitude, (array) $rings[0])) {
            return false;
        }

        foreach (array_slice($rings, 1) as $hole) {
            if ($this->insideRing($longitude, $latitude, (array) $hole)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int,mixed> $ring */
    private function insideRing(float $x, float $y, array $ring): bool
    {
        $count = count($ring);
        if ($count < 4) {
            return false;
        }

        $inside = false;
        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            if (! $this->validPoint($ring[$i] ?? null) || ! $this->validPoint($ring[$j] ?? null)) {
                return false;
            }
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];

            if ($this->onSegment($x, $y, $xi, $yi, $xj, $yj)) {
                return true;
            }

            $crosses = (($yi > $y) !== ($yj > $y))
                && ($x < (($xj - $xi) * ($y - $yi) / ($yj - $yi)) + $xi);
            if ($crosses) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function validPoint(mixed $point): bool
    {
        return is_array($point) && isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1]);
    }

    private function onSegment(float $x, float $y, float $x1, float $y1, float $x2, float $y2): bool
    {
        $cross = ($y - $y1) * ($x2 - $x1) - ($x - $x1) * ($y2 - $y1);
        if (abs($cross) > 1.0e-10) {
            return false;
        }

        return $x >= min($x1, $x2) - 1.0e-10
            && $x <= max($x1, $x2) + 1.0e-10
            && $y >= min($y1, $y2) - 1.0e-10
            && $y <= max($y1, $y2) + 1.0e-10;
    }
}
