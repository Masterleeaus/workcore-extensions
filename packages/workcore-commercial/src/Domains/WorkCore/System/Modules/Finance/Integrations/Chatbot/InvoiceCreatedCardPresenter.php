<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Chatbot;

final class InvoiceCreatedCardPresenter
{
    /** @param array<string,mixed> $actionResult @return array<string,mixed> */
    public function present(array $actionResult): array
    {
        $data = is_array($actionResult['data'] ?? null) ? $actionResult['data'] : [];
        $invoice = is_array($data['invoice']['invoice'] ?? null) ? $data['invoice']['invoice'] : [];
        return [
            'type' => 'titan_money.invoice_card',
            'title' => (bool) ($data['issued'] ?? false) ? 'Invoice issued' : 'Invoice ready',
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'customer' => $invoice['customer_snapshot']['name'] ?? null,
            'total' => $invoice['total'] ?? null,
            'payment_qr' => $data['payment']['qr_artifact'] ?? null,
            'actions' => $actionResult['ui']['actions'] ?? [],
        ];
    }
}
