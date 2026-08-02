<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Onboarding;

use InvalidArgumentException;

final class OnboardingTask
{
    private bool $completed = false;
    private ?string $completedBy = null;

    public function __construct(public readonly string $key, public readonly bool $required)
    {
        if (trim($key) === '') throw new InvalidArgumentException('Onboarding task key is required.');
    }

    public function completed(): bool { return $this->completed; }
    public function complete(string $actorId): void
    {
        if (trim($actorId) === '') throw new InvalidArgumentException('A named actor is required.');
        $this->completed = true;
        $this->completedBy = $actorId;
    }
}
