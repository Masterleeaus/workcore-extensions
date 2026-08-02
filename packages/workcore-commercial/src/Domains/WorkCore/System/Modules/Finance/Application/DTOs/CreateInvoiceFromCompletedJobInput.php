<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use InvalidArgumentException;

final readonly class CreateInvoiceFromCompletedJobInput implements ActionInput
{
    /** @param list<string> $deliveryChannels */
    public function __construct(
        public string $jobId,
        public bool $issueWhenPolicyAllows = true,
        public bool $sendPaymentInstructions = true,
        public PaymentMethodKey $preferredPaymentMethod = PaymentMethodKey::PayId,
        public array $deliveryChannels = ['email', 'chatbot'],
    ) {
        if (trim($jobId) === '') {
            throw new InvalidArgumentException('Job ID must not be blank.');
        }
        if ($sendPaymentInstructions && $deliveryChannels === []) {
            throw new InvalidArgumentException('At least one delivery channel is required.');
        }
    }

    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'issue_when_policy_allows' => $this->issueWhenPolicyAllows,
            'send_payment_instructions' => $this->sendPaymentInstructions,
            'preferred_payment_method' => $this->preferredPaymentMethod->value,
            'delivery_channels' => array_values($this->deliveryChannels),
        ];
    }
}
