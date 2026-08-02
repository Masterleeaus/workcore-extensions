<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Services;

use InvalidArgumentException;

final class AssetStatusPolicy
{
    public const STATES = [
        'ordered', 'received', 'available', 'assigned', 'checked_out', 'in_use',
        'maintenance_due', 'under_maintenance', 'unavailable', 'damaged', 'lost',
        'retired', 'disposed', 'written_off',
    ];

    private const TRANSITIONS = [
        'ordered' => ['received'],
        'received' => ['available', 'damaged', 'unavailable'],
        'available' => ['assigned', 'checked_out', 'in_use', 'maintenance_due', 'under_maintenance', 'unavailable', 'damaged', 'lost', 'retired'],
        'assigned' => ['available', 'checked_out', 'in_use', 'maintenance_due', 'under_maintenance', 'unavailable', 'damaged', 'lost', 'retired'],
        'checked_out' => ['available', 'assigned', 'in_use', 'maintenance_due', 'under_maintenance', 'unavailable', 'damaged', 'lost'],
        'in_use' => ['available', 'assigned', 'checked_out', 'maintenance_due', 'under_maintenance', 'unavailable', 'damaged', 'lost'],
        'maintenance_due' => ['available', 'under_maintenance', 'unavailable', 'retired'],
        'under_maintenance' => ['available', 'assigned', 'unavailable', 'damaged', 'retired'],
        'unavailable' => ['available', 'under_maintenance', 'damaged', 'retired'],
        'damaged' => ['under_maintenance', 'unavailable', 'retired', 'written_off'],
        'lost' => ['available', 'retired', 'written_off'],
        'retired' => ['disposed', 'written_off'],
        'disposed' => [],
        'written_off' => [],
    ];

    private const ASSIGNABLE = ['available', 'assigned'];
    private const TERMINAL = ['lost', 'retired', 'disposed', 'written_off'];
    private const SENSITIVE = ['lost', 'retired', 'disposed', 'written_off'];

    public function assertInitial(string $status): void
    {
        $this->assertKnown($status);
        if (in_array($status, self::TERMINAL, true) || in_array($status, ['checked_out', 'in_use', 'under_maintenance'], true)) {
            throw new InvalidArgumentException("Asset cannot be created directly in {$status} state.");
        }
    }

    public function assertTransition(
        string $from,
        string $to,
        ?string $reason,
        ?int $approvedByUserId = null,
        bool $override = false,
    ): void {
        $this->assertKnown($from);
        $this->assertKnown($to);
        if ($from === $to) {
            return;
        }

        $allowed = in_array($to, self::TRANSITIONS[$from] ?? [], true);
        if (! $allowed && ! $override) {
            throw new InvalidArgumentException("Asset status cannot transition from {$from} to {$to}.");
        }

        $sensitive = in_array($to, self::SENSITIVE, true)
            || in_array($from, self::TERMINAL, true)
            || $override;
        if ($sensitive && (trim((string) $reason) === '' || ($approvedByUserId ?? 0) < 1)) {
            throw new InvalidArgumentException('Sensitive asset transitions require approval and a reason.');
        }
    }

    public function isAssignable(string $status): bool
    {
        return in_array($status, self::ASSIGNABLE, true);
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL, true);
    }

    public function eventFor(string $status): string
    {
        return match ($status) {
            'received' => 'workcore.asset.received',
            'assigned' => 'workcore.asset.assigned',
            'checked_out' => 'workcore.asset.checked_out',
            'maintenance_due' => 'workcore.asset.maintenance_due',
            'under_maintenance' => 'workcore.asset.maintenance_started',
            'lost' => 'workcore.asset.lost',
            'retired' => 'workcore.asset.retired',
            'disposed' => 'workcore.asset.disposed',
            'written_off' => 'workcore.asset.written_off',
            default => 'workcore.asset.status_changed',
        };
    }

    private function assertKnown(string $status): void
    {
        if (! in_array($status, self::STATES, true)) {
            throw new InvalidArgumentException("Unknown asset lifecycle state [{$status}].");
        }
    }
}
