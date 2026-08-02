<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\PayPalWebhookVerifier;

final class FailClosedPayPalWebhookVerifier implements PayPalWebhookVerifier
{
    public function verify(array $headers,string $body):bool{return false;}
}
