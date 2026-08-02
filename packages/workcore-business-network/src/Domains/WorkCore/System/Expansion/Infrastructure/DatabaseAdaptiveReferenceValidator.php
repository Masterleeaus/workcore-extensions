<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Infrastructure;

use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveReferenceValidatorContract;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Throwable;

final class DatabaseAdaptiveReferenceValidator implements AdaptiveReferenceValidatorContract
{
    /** @param array<string,array<string,mixed>> $sources */
    public function __construct(
        private ConnectionInterface $database,
        private array $sources,
    ) {}

    public function assertAccessible(int $companyId, string $referenceType, string $identifier, string $field): void
    {
        $source = (array) ($this->sources[$referenceType] ?? []);
        $table = trim((string) ($source['table'] ?? ''));
        $identifierColumn = trim((string) ($source['identifier_column'] ?? 'public_id'));
        $companyColumn = trim((string) ($source['company_column'] ?? 'company_id'));
        if ($table === '' || $identifierColumn === '' || $companyColumn === '') {
            throw new InvalidArgumentException("Adaptive record reference type [{$referenceType}] is not available.");
        }

        try {
            $query = $this->database->table($table)
                ->where($companyColumn, (string) $companyId)
                ->where($identifierColumn, $identifier);
            if ((bool) ($source['soft_deletes'] ?? false)) {
                $query->whereNull('deleted_at');
            }
            $exists = $query->exists();
        } catch (Throwable) {
            throw new InvalidArgumentException("Adaptive record reference [{$field}] could not be validated.");
        }

        if (! $exists) {
            throw new InvalidArgumentException("Adaptive record reference [{$field}] does not belong to the current company.");
        }
    }
}
