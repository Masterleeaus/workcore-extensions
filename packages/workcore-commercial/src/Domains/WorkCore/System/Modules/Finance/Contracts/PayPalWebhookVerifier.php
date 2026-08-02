<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface PayPalWebhookVerifier
{
    /** @param array<string,string> $headers */
    public function verify(array $headers, string $body): bool;
}
