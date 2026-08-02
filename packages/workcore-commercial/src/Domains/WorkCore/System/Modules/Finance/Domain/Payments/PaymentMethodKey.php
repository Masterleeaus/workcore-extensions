<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

enum PaymentMethodKey: string { case Cash='cash'; case PayId='payid'; case BankTransfer='bank_transfer'; case PayPalCard='paypal_card'; }
