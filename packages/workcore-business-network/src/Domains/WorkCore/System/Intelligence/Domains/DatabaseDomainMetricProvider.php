<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use App\Domains\WorkCore\System\Intelligence\Domains\Contracts\DomainMetricProviderContract;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Throwable;

final readonly class DatabaseDomainMetricProvider implements DomainMetricProviderContract
{
    public function __construct(private ConnectionInterface $connection) {}

    public function measure(int $companyId, array $definitions): array
    {
        $results = [];
        $schema = $this->connection->getSchemaBuilder();

        foreach ($definitions as $definition) {
            try {
                if (! $schema->hasTable($definition->table) || ! $schema->hasColumn($definition->table, 'company_id')) {
                    $results[$definition->key] = null;
                    continue;
                }

                $query = $this->connection->table($definition->table)->where('company_id', $companyId);
                if ($schema->hasColumn($definition->table, 'deleted_at')) {
                    $query->whereNull('deleted_at');
                }
                $this->applyFilters($query, (array) ($definition->options['filters'] ?? []));
                $results[$definition->key] = $this->execute($query, $definition);
            } catch (Throwable) {
                $results[$definition->key] = null;
            }
        }

        return $results;
    }

    private function execute(Builder $query, DomainMetricDefinition $definition): int|float|null
    {
        $column = $definition->column;
        $values = $definition->values;

        $value = match ($definition->operation) {
            'count' => $query->count(),
            'count_where' => $query->whereIn((string) $column, $values)->count(),
            'count_where_not' => $query->whereNotIn((string) $column, $values)->count(),
            'count_where_null' => $query->whereNull((string) $column)->count(),
            'count_where_not_null' => $query->whereNotNull((string) $column)->count(),
            'count_date_before' => $query->where((string) $column, '<', $this->dateValue($values[0] ?? 'now'))->count(),
            'count_date_between' => $query->whereBetween((string) $column, [
                $this->dateValue($values[0] ?? 'now'),
                $this->dateValue($values[1] ?? '+30 days'),
            ])->count(),
            'count_column_compare' => $query->whereColumn(
                (string) $column,
                (string) ($definition->options['compare_operator'] ?? '<='),
                (string) ($definition->options['compare_column'] ?? ''),
            )->count(),
            'sum' => (float) $query->sum((string) ($definition->options['sum_column'] ?? $column)),
            'sum_where' => (float) $query->whereIn((string) $column, $values)->sum((string) ($definition->options['sum_column'] ?? '')),
            'sum_date_between' => (float) $query->whereBetween((string) $column, [
                $this->dateValue($values[0] ?? 'first day of this month'),
                $this->dateValue($values[1] ?? 'last day of this month'),
            ])->sum((string) ($definition->options['sum_column'] ?? '')),
            default => null,
        };

        $multiplier = (float) ($definition->options['multiplier'] ?? 1.0);
        if (is_float($value) && $multiplier !== 1.0) {
            $value *= $multiplier;
        }

        return is_float($value) ? round($value, 2) : $value;
    }

    /** @param list<array<string,mixed>> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $filter) {
            $column = (string) $filter['column'];
            $operator = (string) $filter['operator'];
            $value = $filter['value'] ?? null;

            match ($operator) {
                '=' => $query->where($column, '=', $value),
                '!=' => $query->where($column, '!=', $value),
                '<', '<=', '>', '>=' => $query->where($column, $operator, $this->normaliseValue($value)),
                'in' => $query->whereIn($column, (array) $value),
                'not_in' => $query->whereNotIn($column, (array) $value),
                'null' => $query->whereNull($column),
                'not_null' => $query->whereNotNull($column),
            };
        }
    }

    private function normaliseValue(mixed $value): mixed
    {
        return is_string($value) && str_starts_with($value, '@date:')
            ? $this->dateValue(substr($value, 6))
            : $value;
    }

    private function dateValue(string|int|float|bool|null $expression): string
    {
        $value = trim((string) $expression);
        $date = match ($value) {
            'today' => new DateTimeImmutable('today'),
            'now', '' => new DateTimeImmutable(),
            default => new DateTimeImmutable($value),
        };

        return $date->format('Y-m-d H:i:s');
    }
}
