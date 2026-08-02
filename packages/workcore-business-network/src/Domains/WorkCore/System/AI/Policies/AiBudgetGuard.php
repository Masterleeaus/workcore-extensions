<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Policies;

use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\Exceptions\AiPolicyException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

final class AiBudgetGuard
{
    public function __construct(private ConnectionInterface $db) {}

    public function assertAllowed(ModelRequest $request): void
    {
        $maxOutput = max(1, (int) config('workcore_ai.budgets.max_output_tokens_per_request', 4096));
        if ($request->maxOutputTokens > $maxOutput) {
            throw new AiPolicyException("Requested output token limit exceeds the WorkCore maximum of {$maxOutput}.", 422);
        }

        $daily = (int) config('workcore_ai.budgets.daily_company_tokens', 0);
        if ($daily < 1 || ! Schema::hasTable('tz_ai_usage_ledger')) {
            return;
        }
        $used = (int) $this->db->table('tz_ai_usage_ledger')
            ->where('company_id', $request->companyId)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->sum('total_tokens');
        if ($used >= $daily) {
            throw new AiPolicyException('The company native-AI daily token budget has been reached.', 429);
        }
    }
}
