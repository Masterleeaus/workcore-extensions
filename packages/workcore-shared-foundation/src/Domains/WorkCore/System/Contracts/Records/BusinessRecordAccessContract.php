<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records;

interface BusinessRecordAccessContract
{
    public function recordType(): string;

    public function findByPublicId(string $publicId, int $companyId): ?BusinessRecordSnapshot;

    /** @param array<string,mixed> $filters @return array<int,BusinessRecordSnapshot> */
    public function search(int $companyId, array $filters = [], int $limit = 50): array;
}
