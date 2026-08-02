<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentQrPayload;

interface QrCodeRenderer { public function render(PaymentQrPayload $payload): string; }
