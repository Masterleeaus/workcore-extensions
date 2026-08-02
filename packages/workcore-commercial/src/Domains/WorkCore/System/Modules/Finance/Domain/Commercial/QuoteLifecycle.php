<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial;

final class QuoteLifecycle
{
    public function canTransition(QuoteState $from, QuoteState $to): bool
    {
        return in_array($to, match($from) {
            QuoteState::Draft => [QuoteState::Ready],
            QuoteState::Ready => [QuoteState::Sent, QuoteState::Draft],
            QuoteState::Sent => [QuoteState::Viewed, QuoteState::Revised, QuoteState::Accepted, QuoteState::Declined, QuoteState::Expired],
            QuoteState::Viewed => [QuoteState::Revised, QuoteState::Accepted, QuoteState::Declined, QuoteState::Expired],
            QuoteState::Revised => [QuoteState::Sent, QuoteState::Accepted, QuoteState::Declined, QuoteState::Expired],
            QuoteState::Accepted => [QuoteState::Converted],
            QuoteState::Declined, QuoteState::Expired, QuoteState::Converted => [],
        }, true);
    }
}
