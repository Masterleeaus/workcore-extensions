<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Telemetry;

use App\Domains\WorkCore\System\Intelligence\Safety\ContentSafetyScanner;

final class IntelligenceTraceCollector
{
    /** @var list<IntelligenceTraceEvent> */
    private array $events = [];

    public function __construct(private ContentSafetyScanner $safety, private int $capacity = 200) {}

    /** @param array<string,mixed> $attributes */
    public function record(string $name, array $attributes = [], ?string $correlationId = null, ?string $causationId = null): void
    {
        $sanitized = [];
        foreach ($attributes as $key => $value) {
            if (is_string($value)) {
                $scan = $this->safety->scan($value, 'telemetry');
                $sanitized[$key] = $scan->blocked ? '[BLOCKED_SENSITIVE_CONTENT]' : $scan->sanitized;
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = '[STRUCTURED_VALUE_OMITTED]';
            }
        }
        $this->events[] = new IntelligenceTraceEvent($name, gmdate('c'), $sanitized, $correlationId, $causationId);
        if (count($this->events) > max(10, $this->capacity)) {
            array_shift($this->events);
        }
    }

    /** @return list<IntelligenceTraceEvent> */
    public function events(): array
    {
        return $this->events;
    }
}
