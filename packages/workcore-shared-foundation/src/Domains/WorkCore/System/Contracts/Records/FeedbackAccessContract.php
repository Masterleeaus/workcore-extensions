<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records;

interface FeedbackAccessContract extends BusinessRecordAccessContract
{
    /** @deprecated Use findByPublicId(). */
    public function find(int $companyId, string $publicId): ?array;
}
