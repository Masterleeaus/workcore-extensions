<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Collections;

final readonly class CollectionSuppressionDecision
{
    public function __construct(
        public bool $suppressed,
        public string $code,
        public ?CollectionSuppression $reason = null,
    ) {}
}
