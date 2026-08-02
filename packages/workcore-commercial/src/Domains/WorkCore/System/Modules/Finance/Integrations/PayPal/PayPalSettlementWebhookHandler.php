<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\PayPal;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\IngestPaymentEvidenceInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\InterfaceOrigin;
use App\Domains\WorkCore\System\Modules\Finance\Audit\ActorType;
use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ExternalEventInboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceIngestor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PayPalWebhookVerifier;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\DecimalMoneyParser;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\ExternalEventReceiptStatus;
use InvalidArgumentException;

final class PayPalSettlementWebhookHandler
{
    private DecimalMoneyParser $moneyParser;
    public function __construct(private readonly PayPalWebhookVerifier $verifier,private readonly ExternalEventInboxRepository $inbox,private readonly PaymentEvidenceIngestor $ingestor,private readonly Clock $clock,?DecimalMoneyParser $moneyParser=null)
    {$this->moneyParser=$moneyParser??new DecimalMoneyParser(['AUD'=>2,'USD'=>2,'NZD'=>2,'EUR'=>2,'GBP'=>2,'JPY'=>0]);}

    /** @param array<string,string> $headers @return array<string,mixed> */
    public function handle(string $companyId,array $headers,string $body):array
    {
        if(!$this->verifier->verify(array_change_key_case($headers,CASE_LOWER),$body))return ['status'=>'rejected','code'=>'money.paypal.signature_invalid'];
        $payload=json_decode($body,true,flags:JSON_THROW_ON_ERROR);if(!is_array($payload))throw new InvalidArgumentException('PayPal webhook payload must be an object.');
        $eventId=trim((string)($payload['id']??''));$eventType=trim((string)($payload['event_type']??''));if($eventId===''||$eventType==='')throw new InvalidArgumentException('PayPal webhook identity is required.');
        $hash=hash('sha256',$body);$claim=$this->inbox->claim($companyId,'paypal',$eventId,$eventType,$hash,$this->clock->now());
        if($claim->status===ExternalEventReceiptStatus::Replay)return ['status'=>'success','code'=>'money.paypal.webhook_replayed','external_event_id'=>$eventId];
        if($claim->status===ExternalEventReceiptStatus::Conflict)return ['status'=>'failed','code'=>'money.paypal.webhook_conflict','external_event_id'=>$eventId];
        if($eventType!=='PAYMENT.CAPTURE.COMPLETED'){$this->inbox->complete($companyId,'paypal',$eventId,$this->clock->now());return ['status'=>'ignored','code'=>'money.paypal.event_ignored','external_event_id'=>$eventId];}
        try{
            $resource=is_array($payload['resource']??null)?$payload['resource']:[];$amountData=is_array($resource['amount']??null)?$resource['amount']:[];
            $currency=strtoupper((string)($amountData['currency_code']??''));$money=$this->moneyParser->parse((string)($amountData['value']??''),$currency);
            $nameData=is_array($resource['payer']['name']??null)?$resource['payer']['name']:[];$payer=trim(implode(' ',array_filter([(string)($nameData['given_name']??''),(string)($nameData['surname']??'')])));$payer=$payer===''?null:$payer;
            $reference=trim((string)($resource['custom_id']??$resource['invoice_id']??$resource['id']??$eventId));
            $context=new ActionContext($companyId,null,null,InterfaceOrigin::Integration,null,null,null,null,null,'paypal:'.$eventId,'paypal:'.$eventId,new AuditActor(ActorType::Integration,'paypal','PayPal settlement webhook'));
            $result=$this->ingestor->ingest(new IngestPaymentEvidenceInput('provider_webhook',$money->minorAmount,$currency,$reference,$payer,$hash,$this->clock->now(),null,'paypal_card'),$context);
            $this->inbox->complete($companyId,'paypal',$eventId,$this->clock->now());
            return ['status'=>'success','code'=>'money.paypal.settlement_processed','external_event_id'=>$eventId,'result'=>$result->toArray()];
        }catch(\Throwable $e){$this->inbox->fail($companyId,'paypal',$eventId,'paypal_settlement_failed');throw $e;}
    }
}
