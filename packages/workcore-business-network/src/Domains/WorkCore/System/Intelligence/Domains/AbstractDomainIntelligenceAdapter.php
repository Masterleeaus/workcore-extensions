<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use App\Domains\WorkCore\System\Intelligence\Domains\Contracts\DomainIntelligenceAdapterContract;
use App\Domains\WorkCore\System\Intelligence\Domains\Contracts\DomainMetricProviderContract;
use DateTimeImmutable;

abstract class AbstractDomainIntelligenceAdapter implements DomainIntelligenceAdapterContract
{
    public function __construct(protected DomainMetricProviderContract $metrics) {}

    /** @return list<DomainMetricDefinition> */
    abstract protected function metricDefinitions(): array;

    /**
     * @param array<string,int|float|null> $metrics
     * @return list<DomainSignal>
     */
    abstract protected function signals(array $metrics): array;

    public function context(DomainIntelligenceRequest $request): DomainIntelligenceContext
    {
        $definitions = $this->metricDefinitions();
        $measured = $this->metrics->measure($request->companyId, $definitions);
        $values = [];
        $gaps = [];
        foreach ($definitions as $definition) {
            if (! array_key_exists($definition->key, $measured) || $measured[$definition->key] === null) {
                $gaps[] = "Metric [{$definition->key}] is unavailable.";
                $values[$this->shortKey($definition->key)] = 0;
                continue;
            }
            $values[$this->shortKey($definition->key)] = $measured[$definition->key];
        }

        return new DomainIntelligenceContext(
            domain: $this->key(),
            name: $this->name(),
            capability: $this->capability(),
            companyId: $request->companyId,
            metrics: $values,
            signals: $this->signals($measured),
            actions: $this->actions(),
            dataGaps: $gaps,
            generatedAt: new DateTimeImmutable(),
        );
    }

    protected function value(array $metrics, string $key): float
    {
        return (float) ($metrics[$key] ?? 0);
    }

    /** @return list<array<string,mixed>> */
    protected function evidence(string $metric, int|float $value): array
    {
        return [['metric' => $metric, 'value' => $value, 'source' => 'company_scoped_domain_metric']];
    }

    private function shortKey(string $key): string
    {
        $parts = explode('.', $key);
        return (string) end($parts);
    }
}
