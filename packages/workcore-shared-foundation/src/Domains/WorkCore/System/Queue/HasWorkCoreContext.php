<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Queue;

trait HasWorkCoreContext
{
    /** @var array{tenant:array<string,mixed>,operation:array<string,mixed>} */
    private array $workCoreContextPayload = [];

    protected function captureWorkCoreContext(): void
    {
        $this->workCoreContextPayload = app(WorkCoreContextPayloadFactory::class)->capture();
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
