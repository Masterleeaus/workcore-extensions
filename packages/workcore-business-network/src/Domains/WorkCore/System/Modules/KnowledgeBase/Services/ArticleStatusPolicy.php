<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Services;

use InvalidArgumentException;

final class ArticleStatusPolicy
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'draft' => ['review', 'published', 'archived'],
        'review' => ['draft', 'published', 'archived'],
        'published' => ['retired', 'archived'],
        'retired' => ['draft', 'archived'],
        'archived' => ['draft'],
    ];

    public function assertTransition(string $from, string $to, ?string $reason): void
    {
        if ($from === $to) return;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("Knowledge article cannot transition from {$from} to {$to}.");
        }
        if (($to === 'retired' || $to === 'archived' || in_array($from, ['retired', 'archived'], true)) && trim((string)$reason) === '') {
            throw new InvalidArgumentException('A reason is required when retiring, archiving or reactivating a knowledge article.');
        }
    }
}
