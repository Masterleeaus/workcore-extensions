<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Services;

use InvalidArgumentException;

final class WarrantyClaimPolicy
{
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['assessing', 'rejected'],
        'assessing' => ['approved', 'rejected'],
        'approved' => ['repairing', 'replaced', 'resolved'],
        'repairing' => ['resolved'],
        'replaced' => ['resolved'],
        'rejected' => [],
        'resolved' => [],
    ];

    public function assertInitial(string $state): void
    {
        if (! in_array($state, ['draft', 'submitted'], true)) {
            throw new InvalidArgumentException('A warranty claim must start as draft or submitted.');
        }
    }

    /** @param array<string,mixed> $data */
    public function assertTransition(string $from, string $to, array $data = []): void
    {
        if ($from === $to) {
            return;
        }
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("Warranty claim cannot transition from {$from} to {$to}.");
        }
        if (in_array($to, ['rejected', 'resolved'], true) && trim((string) ($data['resolution_notes'] ?? '')) === '') {
            throw new InvalidArgumentException('Warranty claim resolution requires resolution_notes.');
        }
        if ($to === 'replaced' && empty($data['replacement_asset_public_id'])) {
            throw new InvalidArgumentException('Replacement outcome requires replacement_asset_public_id.');
        }
        if ($to === 'resolved' && empty($data['outcome'])) {
            throw new InvalidArgumentException('Resolved warranty claims require an outcome.');
        }
    }
}
