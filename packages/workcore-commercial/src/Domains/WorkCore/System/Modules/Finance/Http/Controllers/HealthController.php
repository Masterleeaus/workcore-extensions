<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Http\Controllers;

use App\Domains\WorkCore\System\Modules\Finance\Support\TitanMoneyHealth;
use Illuminate\Http\JsonResponse;

final readonly class HealthController
{
    public function __construct(private TitanMoneyHealth $health)
    {
    }

    public function __invoke(): JsonResponse
    {
        $report = $this->health->report();

        return response()->json($report, $report['ready'] ? 200 : 503);
    }
}
