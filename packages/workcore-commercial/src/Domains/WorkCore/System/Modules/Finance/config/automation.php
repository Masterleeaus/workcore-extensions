<?php

declare(strict_types=1);

return [
    'invoice_eligibility' => [
        'require_completed_job' => true,
        'require_billable_lines' => true,
        'require_customer' => true,
        'require_evidence' => true,
        'prevent_duplicate_job_invoice' => true,
    ],
    'payment_methods' => ['cash', 'payid', 'bank_transfer', 'paypal_card'],
    'qr_delivery_channels' => ['email', 'sms', 'whatsapp', 'messenger', 'chatbot', 'customer_portal', 'print', 'titan_go'],
    'collection' => [
        'throttle_hours' => 20,
        'stop_on_payment_claim' => true,
        'stop_on_dispute' => true,
        'stop_on_payment_plan' => true,
        'stop_on_paid' => true,
    ],
];
