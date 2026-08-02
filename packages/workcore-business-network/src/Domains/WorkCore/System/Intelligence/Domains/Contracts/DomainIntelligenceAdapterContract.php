<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Contracts;

use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceContext;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRequest;

interface DomainIntelligenceAdapterContract
{
    public function key(): string;

    public function name(): string;

    public function capability(): string;

    public function viewPermission(): string;

    public function context(DomainIntelligenceRequest $request): DomainIntelligenceContext;

    /** @return list<DomainActionDescriptor> */
    public function actions(): array;
}
