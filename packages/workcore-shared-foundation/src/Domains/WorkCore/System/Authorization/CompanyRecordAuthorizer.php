<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Authorization;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

final class CompanyRecordAuthorizer
{
    /**
     * Apply a WorkSuite-compatible record access level to a tenant-scoped query.
     *
     * The query must already be restricted to the active company. This class only
     * narrows the records visible to the actor according to creator/owner columns.
     *
     * @param list<string> $ownerColumns
     */
    public function apply(
        Builder $query,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        string $createdByColumn = 'created_by_user_id',
        array $ownerColumns = [],
    ): Builder {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('A positive actor ID is required for record-level authorisation.');
        }

        return match ($accessLevel) {
            WorkCoreAccessLevel::All => $query,
            WorkCoreAccessLevel::Added => $query->where($createdByColumn, $actorId),
            WorkCoreAccessLevel::Owned => $this->applyOwned($query, $actorId, $ownerColumns),
            WorkCoreAccessLevel::Both => $this->applyBoth($query, $actorId, $createdByColumn, $ownerColumns),
            WorkCoreAccessLevel::None => $query->whereRaw('1 = 0'),
        };
    }

    /** @param list<string> $ownerColumns */
    private function applyOwned(Builder $query, int $actorId, array $ownerColumns): Builder
    {
        if ($ownerColumns === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $nested) use ($actorId, $ownerColumns): void {
            foreach (array_values($ownerColumns) as $index => $column) {
                $index === 0
                    ? $nested->where($column, $actorId)
                    : $nested->orWhere($column, $actorId);
            }
        });
    }

    /** @param list<string> $ownerColumns */
    private function applyBoth(
        Builder $query,
        int $actorId,
        string $createdByColumn,
        array $ownerColumns,
    ): Builder {
        return $query->where(function (Builder $nested) use ($actorId, $createdByColumn, $ownerColumns): void {
            $nested->where($createdByColumn, $actorId);
            foreach ($ownerColumns as $column) {
                $nested->orWhere($column, $actorId);
            }
        });
    }
}
