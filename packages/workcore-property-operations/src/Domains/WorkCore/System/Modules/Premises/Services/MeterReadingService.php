<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

class MeterReadingService
{
    public function calculate(array $payload): array
    {
        $current = (float) ($payload['current_reading'] ?? 0);
        $previous = array_key_exists('previous_reading', $payload) && $payload['previous_reading'] !== null
            ? (float) $payload['previous_reading']
            : null;

        $consumed = $previous === null ? 0.0 : max(0.0, $current - $previous);

        $rate = array_key_exists('rate', $payload) && $payload['rate'] !== null
            ? (float) $payload['rate']
            : null;

        $amount = $rate === null ? null : round($consumed * $rate, 2);

        $payload['consumed'] = $consumed;
        $payload['amount'] = $amount;

        return $payload;
    }
}
