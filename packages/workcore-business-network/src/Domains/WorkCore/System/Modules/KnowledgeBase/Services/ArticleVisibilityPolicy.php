<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Services;

use InvalidArgumentException;

final class ArticleVisibilityPolicy
{
    public const VISIBILITIES = ['internal','customer','resident','owner','contractor','public'];

    public function assertValid(string $visibility): void
    {
        if (! in_array($visibility, self::VISIBILITIES, true)) throw new InvalidArgumentException('Unsupported knowledge article visibility.');
    }

    /** @param list<string> $audiences */
    public function canRead(string $visibility, array $audiences): bool
    {
        $this->assertValid($visibility);
        return $visibility === 'public' || in_array($visibility, $audiences, true) || in_array('internal', $audiences, true);
    }
}
