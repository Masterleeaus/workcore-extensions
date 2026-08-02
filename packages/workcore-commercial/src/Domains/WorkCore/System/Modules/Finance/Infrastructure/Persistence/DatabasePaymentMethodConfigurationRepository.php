<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMethodConfigurationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\{PaymentMethodConfiguration,PaymentMethodKey};
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class DatabasePaymentMethodConfigurationRepository implements PaymentMethodConfigurationRepository
{
    public function configuration(string $companyId,PaymentMethodKey $method):PaymentMethodConfiguration{$row=DB::table('tm_payment_provider_connections')->where('company_id',$companyId)->where('payment_method',$method->value)->where('state','active')->orderByDesc('updated_at')->first();if(!$row)throw new RuntimeException("Configure {$method->value} payment instructions for the active company before delivery.");$values=json_decode((string)$row->configuration,true,512,JSON_THROW_ON_ERROR);return new PaymentMethodConfiguration($method,is_array($values)?$values:[],true);}
}
