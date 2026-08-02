<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Chatbot;

final class PaymentConfirmedCardPresenter
{
    public function present(array $result): array{$data=$result['data']??[];$payment=$data['payment']??[];$invoice=$data['invoice']??[];return ['type'=>'titan_money.payment_confirmed_card','payment_id'=>$payment['id']??null,'amount_minor'=>$payment['amount_minor']??null,'allocated_minor'=>$payment['allocated_minor']??null,'unallocated_minor'=>$payment['unallocated_minor']??null,'currency_code'=>$payment['currency_code']??null,'invoice_state'=>$invoice['state']??null,'invoice_balance_minor'=>$invoice['balance_minor']??null,'receipt_id'=>$data['receipt_id']??null];}
}
