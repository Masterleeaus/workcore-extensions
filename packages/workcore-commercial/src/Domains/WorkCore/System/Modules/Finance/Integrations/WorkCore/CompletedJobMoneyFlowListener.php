<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\WorkCore;

use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\CreateInvoiceFromCompletedJob;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\CreateInvoiceFromCompletedJobInput;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use InvalidArgumentException;

final class CompletedJobMoneyFlowListener
{
    public function __construct(private readonly CreateInvoiceFromCompletedJob $action)
    {
    }

    /** @param array<string,mixed> $signal */
    public function handle(array $signal, ActionContext $context): array
    {
        $jobId = $signal['payload']['job_id'] ?? $signal['job_id'] ?? null;
        if (! is_string($jobId) || trim($jobId) === '') {
            throw new InvalidArgumentException('work.job.completed signal requires payload.job_id.');
        }
        $method = PaymentMethodKey::tryFrom((string) ($signal['payload']['preferred_payment_method'] ?? 'payid')) ?? PaymentMethodKey::PayId;
        $channels = $signal['payload']['delivery_channels'] ?? ['email', 'chatbot'];
        if (! is_array($channels)) {
            $channels = ['email', 'chatbot'];
        }
        return $this->action->execute(new CreateInvoiceFromCompletedJobInput(
            jobId: $jobId,
            issueWhenPolicyAllows: (bool) ($signal['payload']['issue_when_policy_allows'] ?? true),
            sendPaymentInstructions: (bool) ($signal['payload']['send_payment_instructions'] ?? true),
            preferredPaymentMethod: $method,
            deliveryChannels: array_values(array_filter($channels, 'is_string')),
        ), $context)->toArray();
    }
}
