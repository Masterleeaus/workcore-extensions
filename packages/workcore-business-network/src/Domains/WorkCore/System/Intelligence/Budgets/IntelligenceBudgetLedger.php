<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Budgets;

final class IntelligenceBudgetLedger
{
    private int $calls = 0;
    private int $inputTokens = 0;
    private int $outputTokens = 0;
    private int $costMicros = 0;
    private float $startedAt;

    public function __construct(private IntelligenceBudget $budget)
    {
        $this->startedAt = microtime(true);
    }

    public function recordCall(int $inputTokens, int $outputTokens, int $costMicros): void
    {
        if (min($inputTokens, $outputTokens, $costMicros) < 0) {
            throw new \InvalidArgumentException('Usage values cannot be negative.');
        }

        $elapsedMs = $this->elapsedMs();
        if ($this->calls + 1 > $this->budget->maxCalls) {
            throw new BudgetExceededException('Intelligence call budget exceeded.');
        }
        if ($this->inputTokens + $inputTokens > $this->budget->maxInputTokens) {
            throw new BudgetExceededException('Intelligence input-token budget exceeded.');
        }
        if ($this->outputTokens + $outputTokens > $this->budget->maxOutputTokens) {
            throw new BudgetExceededException('Intelligence output-token budget exceeded.');
        }
        if ($this->costMicros + $costMicros > $this->budget->maxCostMicros) {
            throw new BudgetExceededException('Intelligence cost budget exceeded.');
        }
        if ($elapsedMs > $this->budget->maxElapsedMs) {
            throw new BudgetExceededException('Intelligence elapsed-time budget exceeded.');
        }

        $this->calls++;
        $this->inputTokens += $inputTokens;
        $this->outputTokens += $outputTokens;
        $this->costMicros += $costMicros;
    }

    /** @return array<string,int> */
    public function snapshot(): array
    {
        return [
            'calls' => $this->calls,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cost_micros' => $this->costMicros,
            'elapsed_ms' => $this->elapsedMs(),
        ];
    }

    private function elapsedMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }
}
