<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyClaim;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyKey;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\RequestFingerprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DatabaseIdempotencyStore implements IdempotencyStore
{
    public function __construct(private readonly IdentifierGenerator $ids) {}

    public function claim(string $companyId,string $actionKey,IdempotencyKey $key,RequestFingerprint $fingerprint): IdempotencyClaim
    {
        try {
            DB::table('tm_idempotency_keys')->insert([
                'id'=>$this->ids->uuid(),'company_id'=>$companyId,'action_key'=>$actionKey,'idempotency_key_hash'=>$key->hash,'request_fingerprint'=>$fingerprint->hash,'status'=>'processing',
                'created_by_actor_type'=>'system','created_by_actor_id'=>'titan-money-idempotency','updated_by_actor_type'=>'system','updated_by_actor_id'=>'titan-money-idempotency','correlation_id'=>'idempotency:'.$key->hash,'origin'=>'system','locked_at'=>now(),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return IdempotencyClaim::acquired();
        } catch (QueryException) {
            $row=DB::table('tm_idempotency_keys')->where('company_id',$companyId)->where('action_key',$actionKey)->where('idempotency_key_hash',$key->hash)->first();
            if($row===null) throw new \RuntimeException('Idempotency claim could not be read after conflict.');
            if(!hash_equals((string)$row->request_fingerprint,$fingerprint->hash)) return IdempotencyClaim::conflict();
            if($row->status==='completed') return IdempotencyClaim::replay($row->result_payload?json_decode($row->result_payload,true,512,JSON_THROW_ON_ERROR):[]);
            if($row->status==='failed') { DB::table('tm_idempotency_keys')->where('id',$row->id)->update(['status'=>'processing','result_payload'=>null,'locked_at'=>now(),'updated_at'=>now()]); return IdempotencyClaim::acquired(); }
            return IdempotencyClaim::inProgress();
        }
    }
    public function complete(string $companyId,string $actionKey,IdempotencyKey $key,array $resultPayload): void
    {
        DB::table('tm_idempotency_keys')->where('company_id',$companyId)->where('action_key',$actionKey)->where('idempotency_key_hash',$key->hash)->update(['status'=>'completed','result_payload'=>json_encode($resultPayload,JSON_THROW_ON_ERROR),'completed_at'=>now(),'updated_at'=>now()]);
    }
    public function fail(string $companyId,string $actionKey,IdempotencyKey $key,string $failureCode): void
    {
        DB::table('tm_idempotency_keys')->where('company_id',$companyId)->where('action_key',$actionKey)->where('idempotency_key_hash',$key->hash)->update(['status'=>'failed','result_payload'=>json_encode(['failure_code'=>$failureCode],JSON_THROW_ON_ERROR),'updated_at'=>now()]);
    }
}
