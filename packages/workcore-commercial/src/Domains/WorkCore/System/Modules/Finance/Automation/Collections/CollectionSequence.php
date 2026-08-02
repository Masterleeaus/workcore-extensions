<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Collections;

final readonly class CollectionSequence
{
    /** @param list<CollectionSequenceStep> $steps */
    public function __construct(public array $steps) {}

    public static function australianDefault(): self
    {
        return new self([
            new CollectionSequenceStep(-3,'due_soon','helpful',['email','chatbot']),
            new CollectionSequenceStep(0,'due_today','friendly',['email','chatbot']),
            new CollectionSequenceStep(3,'friendly_reminder','friendly',['email','sms']),
            new CollectionSequenceStep(7,'direct_reminder','direct',['email','sms','chatbot']),
            new CollectionSequenceStep(14,'firm_reminder','firm',['email','task']),
            new CollectionSequenceStep(21,'payment_plan_offer','supportive',['email','task'],true),
            new CollectionSequenceStep(30,'escalation_review','escalation',['task'],true),
        ]);
    }

    /** @param list<string> $completedCodes @param list<CollectionSuppression> $suppressions */
    public function nextStep(int $daysOverdue, array $completedCodes, array $suppressions): ?CollectionSequenceStep
    {
        if ($suppressions!==[]) return null;
        $candidate=null;
        foreach ($this->steps as $step) {
            if ($step->dueDay <= $daysOverdue && !in_array($step->code,$completedCodes,true)) $candidate=$step;
        }
        return $candidate;
    }
}
