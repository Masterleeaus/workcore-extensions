<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Collections;

use DateTimeImmutable;

final class CollectionSuppressionEvaluator
{
    public function evaluate(CollectionAccountState $state, DateTimeImmutable $now): CollectionSuppressionDecision
    {
        $checks = [
            [$state->paid || $state->balanceMinor === 0, CollectionSuppression::Paid],
            [$state->disputeOpen, CollectionSuppression::DisputeOpen],
            [$state->paymentPlanActive, CollectionSuppression::PaymentPlanActive],
            [$state->paymentClaimPending, CollectionSuppression::PaymentClaimPending],
            [$state->paused, CollectionSuppression::Paused],
            [$state->voided, CollectionSuppression::Voided],
            [$state->credited, CollectionSuppression::Credited],
        ];
        foreach ($checks as [$blocked, $reason]) {
            if ($blocked) {
                return new CollectionSuppressionDecision(true, $reason->value, $reason);
            }
        }
        if ($state->promiseDueAt !== null && $state->promiseDueAt >= $now) {
            return new CollectionSuppressionDecision(true, 'promise_to_pay_active');
        }
        return new CollectionSuppressionDecision(false, 'send');
    }
}
