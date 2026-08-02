<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Services;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Verticals\CompanyVerticalProfileResolver;
use App\Domains\WorkCore\System\Verticals\Workflows\WorkflowResolver;
use InvalidArgumentException;
use Throwable;

final class WorkOrderStatusPolicy
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'draft' => ['ready', 'cancelled'],
        'ready' => ['in_progress', 'cancelled'],
        'in_progress' => ['paused', 'completed', 'cancelled'],
        'paused' => ['in_progress', 'cancelled'],
        'completed' => ['in_progress'],
        'cancelled' => ['draft'],
    ];

    public function __construct(
        private TenantContextContract $tenant,
        private CompanyVerticalProfileResolver $profiles,
        private WorkflowResolver $workflows,
    ) {}

    public function assertTransition(string $fromStatus, string $toStatus, ?string $reason, int $incompleteRequiredTasks): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }
        if (! $this->allows($fromStatus, $toStatus)) {
            throw new InvalidArgumentException("Work order cannot move from {$fromStatus} to {$toStatus}.");
        }
        if (($toStatus === 'cancelled' || $fromStatus === 'completed' || $fromStatus === 'cancelled') && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when cancelling or reopening a closed work order.');
        }
        if ($toStatus === 'completed' && $incompleteRequiredTasks > 0) {
            throw new InvalidArgumentException('All required work-order tasks must be completed or skipped before completion.');
        }
    }

    private function allows(string $fromStatus, string $toStatus): bool
    {
        $fallback = in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
        if (! $this->tenant->hasTenant()) {
            return $fallback;
        }

        try {
            $profile = $this->profiles->resolve($this->tenant->companyId());
            if (! $profile->isConfigured() || $profile->workflow('work_order.status') === []) {
                return $fallback;
            }
            return $this->workflows->allows($profile, 'work_order.status', $fromStatus, $toStatus);
        } catch (Throwable $exception) {
            if (! (bool) config('workcore.verticals.legacy_allow_when_unconfigured', true)) {
                throw $exception;
            }
            return $fallback;
        }
    }
}
