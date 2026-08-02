<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Http\Controllers;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\PayPalConnectionResolver;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\PayPal\PayPalSettlementWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class PayPalWebhookController
{
    public function __construct(private PayPalConnectionResolver $companies, private PayPalSettlementWebhookHandler $handler) {}

    public function __invoke(Request $request, string $connectionKey): JsonResponse
    {
        $companyId = $this->companies->resolveCompanyId($connectionKey);
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }
        $result = $this->handler->handle($companyId, $headers, (string) $request->getContent());
        $status = match ($result['code'] ?? '') {
            'money.paypal.signature_invalid' => 401,
            'money.paypal.webhook_conflict' => 409,
            default => 200,
        };
        return response()->json($result, $status);
    }
}
