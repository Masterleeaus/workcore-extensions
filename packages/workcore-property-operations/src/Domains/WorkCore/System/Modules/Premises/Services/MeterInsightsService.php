<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyMeterReading;

class MeterInsightsService
{
    /**
     * Build lightweight insights for the Utilities (Meter Readings) tab.
     * No heavy aggregation; everything tenant-scoped by property/company.
     */
    public function insights(Property $property, int $limit = 50): array
    {
        $companyId = (int) company()->id;

        $recent = PropertyMeterReading::query()
            ->where('company_id', $companyId)
            ->where('property_id', (int) $property->id)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $latestByType = $recent->groupBy(fn($r) => $this->key($r->meter_type, $r->unit_id))
            ->map(fn(Collection $rows) => $rows->first())
            ->sortBy(fn($r) => $r->meter_type)
            ->values();

        $anomalies = $this->detectAnomalies($recent);

        // 30-day totals (by meter_type, property-level)
        $from = Carbon::now()->subDays(30)->startOfDay();
        $totals30d = $recent
            ->filter(fn($r) => $r->reading_date && Carbon::parse($r->reading_date)->gte($from))
            ->groupBy('meter_type')
            ->map(fn(Collection $rows) => [
                'meter_type' => (string) $rows->first()->meter_type,
                'consumed' => (float) round($rows->sum('consumed'), 3),
                'amount' => $rows->contains(fn($r) => $r->amount !== null) ? round($rows->sum(fn($r) => (float) ($r->amount ?? 0)), 2) : null,
            ])
            ->values();

        return [
            'recent' => $recent,
            'latestByType' => $latestByType,
            'totals30d' => $totals30d,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Detect anomalies from recent readings:
     * - compares last two readings per (meter_type, unit_id)
     * - flags large % change in consumed
     */
    public function detectAnomalies(Collection $recent, float $pctThreshold = 30.0, float $minAbsDelta = 10.0): Collection
    {
        $grouped = $recent->groupBy(fn($r) => $this->key($r->meter_type, $r->unit_id));

        return $grouped->map(function (Collection $rows) use ($pctThreshold, $minAbsDelta) {
            $rows = $rows->sortByDesc('reading_date')->sortByDesc('id')->values();
            if ($rows->count() < 2) {
                return null;
            }

            $a = $rows[0];
            $b = $rows[1];

            $consA = (float) ($a->consumed ?? 0);
            $consB = (float) ($b->consumed ?? 0);

            $delta = $consA - $consB;
            $absDelta = abs($delta);

            // avoid divide-by-zero by anchoring to max(consB, 1)
            $pct = ($consB <= 0.0) ? ($consA > 0.0 ? 100.0 : 0.0) : (($delta / $consB) * 100.0);

            if ($absDelta < $minAbsDelta && abs($pct) < $pctThreshold) {
                return null;
            }
            if (abs($pct) < $pctThreshold) {
                return null;
            }

            return [
                'meter_type' => (string) $a->meter_type,
                'unit_id' => $a->unit_id,
                'latest_reading_id' => (int) $a->id,
                'latest_date' => (string) $a->reading_date,
                'latest_consumed' => $consA,
                'previous_consumed' => $consB,
                'delta' => round($delta, 3),
                'pct_change' => round($pct, 1),
            ];
        })->filter()->values();
    }

    private function key(?string $meterType, $unitId): string
    {
        return (string) ($meterType ?? 'other') . ':' . ((string) ($unitId ?? ''));
    }
}
