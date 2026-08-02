<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class WorkCoreJob implements CarriesWorkCoreContext, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array{tenant:array<string,mixed>,operation:array<string,mixed>} */
    private array $workCoreContextPayload;

    public function __construct(?WorkCoreContextPayloadFactory $factory = null)
    {
        $this->workCoreContextPayload = ($factory ?? app(WorkCoreContextPayloadFactory::class))->capture();
    }

    public function workCoreContextPayload(): array
    {
        return $this->workCoreContextPayload;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RestoreWorkCoreContext()];
    }
}
