<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Services;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionSequence;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationScheduler;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceRecord;

final class InvoiceCollectionScheduler
{
    public function __construct(
        private readonly AutomationScheduler $scheduler,
        private readonly CollectionSequence $sequence,
        private readonly Clock $clock,
    ) {
    }

    /** @return list<string> */
    public function schedule(InvoiceRecord $invoice, ActionContext $context): array
    {
        $ids = [];
        foreach ($this->sequence->steps as $step) {
            $target = $invoice->draft->dueDate->modify(($step->dueDay >= 0 ? '+' : '') . $step->dueDay . ' days')->setTime(9, 0);
            $runAt = $target < $this->clock->now() ? $this->clock->now() : $target;
            $ids[] = $this->scheduler->schedule(
                'money.collection.send_followup',
                [
                    'invoice_id' => $invoice->id(),
                    'step' => $step->toArray(),
                ],
                $runAt,
                $context,
            );
        }
        return $ids;
    }
}
