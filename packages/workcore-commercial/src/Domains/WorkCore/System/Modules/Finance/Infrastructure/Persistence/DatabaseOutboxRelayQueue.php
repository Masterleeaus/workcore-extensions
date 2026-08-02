<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRelayQueue;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxRelayRecord;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseOutboxRelayQueue implements OutboxRelayQueue
{
    public function claimPending(DateTimeImmutable $now,int $limit,string $workerId,int $leaseSeconds):array{return DB::transaction(function()use($now,$limit,$workerId,$leaseSeconds):array{$rows=DB::table('tm_outbox_events')->where(function($q)use($now){$q->where('state','pending')->orWhere(function($recovery)use($now){$recovery->where('state','publishing')->where('leased_until','<',$now);});})->where(function($q)use($now){$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',$now);})->orderBy('occurred_at')->limit($limit)->lockForUpdate()->get();$out=[];$until=$now->modify("+$leaseSeconds seconds");foreach($rows as $row){$token=hash('sha256',$workerId.'|'.$row->id.'|'.$now->format(DATE_ATOM));DB::table('tm_outbox_events')->where('id',$row->id)->update(['state'=>'publishing','lease_token'=>$token,'leased_until'=>$until,'publish_attempts'=>((int)$row->publish_attempts)+1,'updated_at'=>now()]);$payload=json_decode((string)$row->payload,true,flags:JSON_THROW_ON_ERROR);$out[]=new OutboxRelayRecord((string)$row->id,(string)$row->company_id,(string)$row->event_type,(string)$row->aggregate_type,(string)$row->aggregate_id,is_array($payload)?$payload:[],(string)$row->correlation_id,new DateTimeImmutable((string)$row->occurred_at),(int)$row->publish_attempts);}return $out;});}
    public function published(string $id,DateTimeImmutable $publishedAt,?string $externalId=null):void{DB::table('tm_outbox_events')->where('id',$id)->update(['state'=>'published','published_at'=>$publishedAt,'external_delivery_id'=>$externalId,'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);}
    public function retry(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $nextAttemptAt):void{DB::table('tm_outbox_events')->where('id',$id)->update(['state'=>'pending','next_attempt_at'=>$nextAttemptAt,'last_error_code'=>$errorCode,'last_error_message'=>$errorMessage,'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);}
    public function deadLetter(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $failedAt):void{DB::table('tm_outbox_events')->where('id',$id)->update(['state'=>'dead_letter','last_error_code'=>$errorCode,'last_error_message'=>$errorMessage,'failed_at'=>$failedAt,'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);}
}
