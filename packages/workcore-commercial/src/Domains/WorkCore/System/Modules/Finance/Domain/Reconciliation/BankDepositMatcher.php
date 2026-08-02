<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

final class BankDepositMatcher
{
    /** @param iterable<OpenReceivableCandidate> $candidates @return list<PaymentMatchCandidate> */
    public function rank(BankDepositEvidence $evidence, iterable $candidates, bool $duplicateEvidence = false): array
    {
        $results=[];
        foreach ($candidates as $candidate) {
            $score=0; $reasons=[];
            if ($evidence->amount->equals($candidate->amountDue)) { $score+=55; $reasons[]='exact_amount'; }
            elseif ($evidence->amount->currency->equals($candidate->amountDue->currency)) {
                $delta=abs($evidence->amount->minorAmount-$candidate->amountDue->minorAmount);
                if ($evidence->amount->minorAmount > $candidate->amountDue->minorAmount && $delta <= max(5000, intdiv($candidate->amountDue->minorAmount, 2))) { $score+=50; $reasons[]='covers_balance'; }
                elseif ($delta <= max(100, intdiv(($candidate->amountDue->minorAmount * 2) + 99, 100))) { $score+=20; $reasons[]='near_amount'; }
            }
            $reference=strtolower(trim($candidate->paymentReference));
            $observed=strtolower($evidence->referenceText);
            if ($reference!=='' && str_contains($observed,$reference)) { $score+=35; $reasons[]='reference_match'; }
            if ($evidence->payerHint && $candidate->payerHint && strtolower(trim($evidence->payerHint))===strtolower(trim($candidate->payerHint))) { $score+=10; $reasons[]='payer_match'; }
            $ageSeconds=$evidence->observedAt->getTimestamp()-$candidate->issuedAt->getTimestamp();
            if ($ageSeconds>=0 && $ageSeconds<=120*86400) { $score+=5; $reasons[]='valid_time_window'; }
            if ($duplicateEvidence) { $score=max(0,$score-70); $reasons[]='duplicate_evidence'; }
            $score=min(100,$score);
            $status=$score>=70 ? MatchStatus::Proposed : ($score>=30 ? MatchStatus::NeedsReview : MatchStatus::Rejected);
            $results[]=new PaymentMatchCandidate($candidate->invoiceId,$score,$status,$reasons,!$duplicateEvidence && $score>=95);
        }
        usort($results, static fn(PaymentMatchCandidate $a, PaymentMatchCandidate $b): int => $b->confidenceScore <=> $a->confidenceScore);
        return $results;
    }
}
