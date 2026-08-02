<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Minimal RRULE helper (safe + dependency-free).
 *
 * Supported (subset):
 * - FREQ=DAILY|WEEKLY|MONTHLY
 * - INTERVAL=n
 * - BYDAY=MO,TU,WE,TH,FR,SA,SU (weekly)
 *
 * Notes:
 * - We intentionally keep this conservative to avoid edge-case surprises.
 * - If an RRULE is missing/invalid, we fall back to single next occurrence from starts_on.
 */
class RecurrenceService
{
    public function isValidRrule(?string $rrule): bool
    {
        if (!$rrule) return true;
        return str_contains($rrule, 'FREQ=');
    }

    public function parse(?string $rrule): array
    {
        if (!$rrule) return [];
        $parts = [];
        foreach (explode(';', strtoupper(trim($rrule))) as $kv) {
            if (!str_contains($kv, '=')) continue;
            [$k,$v] = array_map('trim', explode('=', $kv, 2));
            $parts[$k] = $v;
        }
        return $parts;
    }

    /**
     * Generate upcoming occurrence datetimes.
     * @return DateTimeImmutable[]
     */
    public function nextOccurrences(?string $rrule, DateTimeInterface $start, DateTimeInterface $from, DateTimeInterface $until, int $limit = 50): array
    {
        $cfg = $this->parse($rrule);

        $freq = $cfg['FREQ'] ?? null;
        $interval = max(1, (int)($cfg['INTERVAL'] ?? 1));
        $byday = isset($cfg['BYDAY']) ? array_filter(array_map('trim', explode(',', $cfg['BYDAY']))) : [];

        $startDt = DateTimeImmutable::createFromInterface($start);
        $fromDt  = DateTimeImmutable::createFromInterface($from);
        $untilDt = DateTimeImmutable::createFromInterface($until);

        // If no RRULE or invalid, return one occurrence if within window
        if (!$freq) {
            $one = $startDt;
            if ($one >= $fromDt && $one <= $untilDt) return [$one];
            return [];
        }

        $out = [];
        $cursor = $startDt;

        // Move cursor forward near 'from' (simple fast-forward)
        if ($cursor < $fromDt) {
            $cursor = $this->fastForward($freq, $cursor, $fromDt, $interval);
        }

        while (count($out) < $limit && $cursor <= $untilDt) {
            if ($cursor >= $fromDt) {
                if ($freq === 'WEEKLY' && $byday) {
                    // For weekly with BYDAY, emit the matching days within the current week-window.
                    $out = array_merge($out, $this->emitWeeklyByDay($cursor, $fromDt, $untilDt, $byday, $limit - count($out)));
                    $cursor = $cursor->add(new DateInterval('P' . (7 * $interval) . 'D'));
                    continue;
                }

                $out[] = $cursor;
            }

            $cursor = match ($freq) {
                'DAILY'   => $cursor->add(new DateInterval('P' . $interval . 'D')),
                'WEEKLY'  => $cursor->add(new DateInterval('P' . (7 * $interval) . 'D')),
                'MONTHLY' => $cursor->add(new DateInterval('P' . $interval . 'M')),
                default   => $cursor->add(new DateInterval('P' . $interval . 'D')),
            };
        }

        // Sort and trim
        usort($out, fn($a,$b) => $a <=> $b);
        return array_slice($out, 0, $limit);
    }

    protected function fastForward(string $freq, DateTimeImmutable $cursor, DateTimeImmutable $target, int $interval): DateTimeImmutable
    {
        // Conservative loop (avoids integer overflow / complex calendar math)
        $guard = 0;
        while ($cursor < $target && $guard++ < 5000) {
            $cursor = match ($freq) {
                'DAILY'   => $cursor->add(new DateInterval('P' . $interval . 'D')),
                'WEEKLY'  => $cursor->add(new DateInterval('P' . (7 * $interval) . 'D')),
                'MONTHLY' => $cursor->add(new DateInterval('P' . $interval . 'M')),
                default   => $cursor->add(new DateInterval('P' . $interval . 'D')),
            };
        }
        return $cursor;
    }

    /**
     * Emit days in the current week matching BYDAY.
     * Cursor date is treated as the anchor week.
     * @return DateTimeImmutable[]
     */
    protected function emitWeeklyByDay(DateTimeImmutable $cursor, DateTimeImmutable $from, DateTimeImmutable $until, array $byday, int $limit): array
    {
        $map = ['MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6,'SU'=>7];
        $days = array_values(array_filter(array_map(fn($d) => $map[$d] ?? null, $byday)));
        sort($days);

        // Start of ISO week (Mon)
        $dow = (int)$cursor->format('N');
        $weekStart = $cursor->sub(new DateInterval('P' . ($dow - 1) . 'D'));

        $out = [];
        foreach ($days as $d) {
            if (count($out) >= $limit) break;
            $dt = $weekStart->add(new DateInterval('P' . ($d - 1) . 'D'));
            // Keep same time as cursor
            $dt = $dt->setTime((int)$cursor->format('H'), (int)$cursor->format('i'), 0);
            if ($dt >= $from && $dt <= $until) $out[] = $dt;
        }
        return $out;
    }
}
