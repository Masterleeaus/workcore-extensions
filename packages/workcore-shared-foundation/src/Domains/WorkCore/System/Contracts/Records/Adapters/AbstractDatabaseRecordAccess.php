<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\BusinessRecordSnapshot;
use Illuminate\Database\ConnectionInterface;

abstract class AbstractDatabaseRecordAccess
{
    public function __construct(protected ConnectionInterface $db) {}

    abstract protected function table(): string;
    abstract protected function type(): string;

    /** @return array<int,string> */
    protected function columns(): array
    {
        return ['public_id'];
    }

    public function recordType(): string
    {
        return $this->type();
    }

    public function findByPublicId(string $publicId, int $companyId): ?BusinessRecordSnapshot
    {
        $row = $this->baseQuery($companyId)->where('public_id', $publicId)->first($this->columns());
        return $row ? $this->snapshot((array) $row, $companyId) : null;
    }

    public function search(int $companyId, array $filters = [], int $limit = 50): array
    {
        $query = $this->baseQuery($companyId);
        if (($filters['q'] ?? null) !== null && in_array('name', $this->columns(), true)) {
            $query->where('name', 'like', '%' . trim((string) $filters['q']) . '%');
        }
        return $query->limit(max(1, min($limit, 100)))->get($this->columns())
            ->map(fn ($row): BusinessRecordSnapshot => $this->snapshot((array) $row, $companyId))->all();
    }

    protected function baseQuery(int $companyId): \Illuminate\Database\Query\Builder
    {
        $query = $this->db->table($this->table())->where('company_id', $companyId);
        if ($this->db->getSchemaBuilder()->hasColumn($this->table(), 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        return $query;
    }

    /** @param array<string,mixed> $row */
    protected function snapshot(array $row, int $companyId): BusinessRecordSnapshot
    {
        $publicId = (string) ($row['public_id'] ?? '');
        unset($row['public_id'], $row['company_id']);
        return new BusinessRecordSnapshot($this->type(), $publicId, $companyId, $row);
    }
}
