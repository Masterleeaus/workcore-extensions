<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use InvalidArgumentException;

final readonly class DomainMetricDefinition
{
    private const OPERATIONS = [
        'count',
        'count_where',
        'count_where_not',
        'count_where_null',
        'count_where_not_null',
        'count_date_before',
        'count_date_between',
        'count_column_compare',
        'sum',
        'sum_where',
        'sum_date_between',
    ];

    /**
     * @param list<string|int|float|bool|null> $values
     * @param array<string,mixed> $options
     */
    public function __construct(
        public string $key,
        public string $table,
        public string $operation = 'count',
        public ?string $column = null,
        public array $values = [],
        public array $options = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_.]{1,119}$/', $key)) {
            throw new InvalidArgumentException('Metric keys must be stable dotted identifiers.');
        }
        $this->assertIdentifier($table, 'table');
        if (! in_array($operation, self::OPERATIONS, true)) {
            throw new InvalidArgumentException("Unsupported domain metric operation [{$operation}].");
        }
        if ($column !== null) {
            $this->assertIdentifier($column, 'column');
        }
        if (in_array($operation, ['count_where', 'count_where_not', 'count_where_null', 'count_where_not_null', 'count_date_before', 'count_date_between', 'count_column_compare', 'sum_where', 'sum_date_between'], true) && $column === null) {
            throw new InvalidArgumentException("Metric operation [{$operation}] requires a column.");
        }
        if ($operation === 'count_column_compare') {
            $compareColumn = (string) ($options['compare_column'] ?? '');
            $compareOperator = (string) ($options['compare_operator'] ?? '<=');
            if ($compareColumn === '' || ! in_array($compareOperator, ['<', '<=', '=', '>=', '>'], true)) {
                throw new InvalidArgumentException('Column comparison metrics require a safe column and operator.');
            }
        }
        if (in_array($operation, ['sum', 'sum_where', 'sum_date_between'], true) && trim((string) ($options['sum_column'] ?? $column ?? '')) === '') {
            throw new InvalidArgumentException('Sum metrics require a sum column.');
        }
        foreach (['sum_column', 'compare_column', 'date_column'] as $option) {
            if (isset($options[$option]) && $options[$option] !== null) {
                $this->assertIdentifier((string) $options[$option], $option);
            }
        }
        foreach ((array) ($options['filters'] ?? []) as $filter) {
            if (! is_array($filter) || ! isset($filter['column'], $filter['operator'])) {
                throw new InvalidArgumentException('Metric filters require a column and operator.');
            }
            $this->assertIdentifier((string) $filter['column'], 'filter column');
            if (! in_array((string) $filter['operator'], ['=', '!=', 'in', 'not_in', 'null', 'not_null', '<', '<=', '>', '>='], true)) {
                throw new InvalidArgumentException('Unsupported metric filter operator.');
            }
        }
    }

    private function assertIdentifier(string $value, string $label): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value)) {
            throw new InvalidArgumentException("Unsafe {$label} identifier [{$value}].");
        }
    }
}
