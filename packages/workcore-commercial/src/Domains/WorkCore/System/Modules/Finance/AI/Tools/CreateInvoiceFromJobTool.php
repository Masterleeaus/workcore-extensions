<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\AI\Tools;

use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\CreateInvoiceFromCompletedJob;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\CreateInvoiceFromCompletedJobInput;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;

final class CreateInvoiceFromJobTool
{
    public function __construct(private readonly CreateInvoiceFromCompletedJob $action)
    {
    }

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'name' => 'money.create_invoice_from_job',
            'description' => 'Create an invoice from a completed WorkCore job, applying Titan Money policy, ledger posting, QR payment instructions and collection scheduling.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['job_id'],
                'properties' => [
                    'job_id' => ['type' => 'string'],
                    'issue_when_policy_allows' => ['type' => 'boolean', 'default' => true],
                    'send_payment_instructions' => ['type' => 'boolean', 'default' => true],
                    'preferred_payment_method' => ['enum' => ['cash', 'payid', 'bank_transfer', 'paypal_card']],
                    'delivery_channels' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'additionalProperties' => false,
            ],
            'risk' => 'medium',
        ];
    }

    /** @param array<string,mixed> $arguments @return array<string,mixed> */
    public function invoke(array $arguments, ActionContext $context): array
    {
        $method = PaymentMethodKey::tryFrom((string) ($arguments['preferred_payment_method'] ?? 'payid')) ?? PaymentMethodKey::PayId;
        return $this->action->execute(new CreateInvoiceFromCompletedJobInput(
            jobId: (string) ($arguments['job_id'] ?? ''),
            issueWhenPolicyAllows: (bool) ($arguments['issue_when_policy_allows'] ?? true),
            sendPaymentInstructions: (bool) ($arguments['send_payment_instructions'] ?? true),
            preferredPaymentMethod: $method,
            deliveryChannels: is_array($arguments['delivery_channels'] ?? null) ? array_values($arguments['delivery_channels']) : ['email', 'chatbot'],
        ), $context)->toArray();
    }
}
