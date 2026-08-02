<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime\ScheduledActionRecord;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ScheduledActionQueue;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseScheduledActionQueue implements ScheduledActionQueue
{
    public function claimDue(DateTimeImmutable $now,int $limit,string $workerId,int $leaseSeconds):array
    {
        return DB::transaction(function()use($now,$limit,$workerId,$leaseSeconds):array{
            $rows=DB::table('tm_scheduled_actions')->where('run_at','<=',$now)->where(function($q)use($now){$q->where('state','scheduled')->orWhere(function($recovery)use($now){$recovery->where('state','processing')->where('leased_until','<',$now);});})->orderBy('run_at')->limit($limit)->lockForUpdate()->get();
            $records=[];$leasedUntil=$now->modify("+$leaseSeconds seconds");foreach($rows as $row){$token=hash('sha256',$workerId.'|'.$row->id.'|'.$now->format(DATE_ATOM));DB::table('tm_scheduled_actions')->where('id',$row->id)->update(['state'=>'processing','lease_token'=>$token,'leased_until'=>$leasedUntil,'attempt_count'=>((int)$row->attempt_count)+1,'updated_at'=>now()]);$payload=json_decode((string)$row->payload,true,flags:JSON_THROW_ON_ERROR);$records[]=new ScheduledActionRecord((string)$row->id,(string)$row->company_id,(string)$row->action_key,is_array($payload)?$payload:[],(int)$row->attempt_count,(string)($row->workflow_id?:'' )?:null,(string)$row->correlation_id,new DateTimeImmutable((string)$row->run_at));}return $records;
        });
    }
    public function complete(string $id,array $result,DateTimeImmutable $finishedAt):void{DB::table('tm_scheduled_actions')->where('id',$id)->update(['state'=>'completed','completed_at'=>$finishedAt,'result_payload'=>json_encode($result,JSON_THROW_ON_ERROR),'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);}
    public function retry(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $nextRunAt):void{DB::table('tm_scheduled_actions')->where('id',$id)->update(['state'=>'scheduled','run_at'=>$nextRunAt,'last_error_code'=>$errorCode,'last_error_message'=>$errorMessage,'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);}
    public function fail(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $finishedAt):void{DB::table('tm_scheduled_actions')->where('id',$id)->update(['state'=>'failed','last_error_code'=>$errorCode,'last_error_message'=>$errorMessage,'completed_at'=>$finishedAt,'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);}
}
