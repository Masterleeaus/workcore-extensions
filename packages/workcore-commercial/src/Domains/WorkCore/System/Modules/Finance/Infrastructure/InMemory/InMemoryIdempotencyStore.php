<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyClaim;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyKey;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\RequestFingerprint;

final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string,array{fingerprint:string,status:string,result:?array}> */ public array $records = [];
    public function claim(string $companyId, string $actionKey, IdempotencyKey $key, RequestFingerprint $fingerprint): IdempotencyClaim
    {
        $index = $companyId.'|'.$actionKey.'|'.$key->hash;
        $record = $this->records[$index] ?? null;
        if ($record === null) { $this->records[$index] = ['fingerprint'=>$fingerprint->hash,'status'=>'processing','result'=>null]; return IdempotencyClaim::acquired(); }
        if ($record['fingerprint'] !== $fingerprint->hash) return IdempotencyClaim::conflict();
        if ($record['status'] === 'completed') return IdempotencyClaim::replay($record['result'] ?? []);
        if ($record['status'] === 'failed') { $this->records[$index]['status']='processing'; return IdempotencyClaim::acquired(); }
        return IdempotencyClaim::inProgress();
    }
    public function complete(string $companyId, string $actionKey, IdempotencyKey $key, array $resultPayload): void { $i=$companyId.'|'.$actionKey.'|'.$key->hash; $this->records[$i]['status']='completed'; $this->records[$i]['result']=$resultPayload; }
    public function fail(string $companyId, string $actionKey, IdempotencyKey $key, string $failureCode): void { $i=$companyId.'|'.$actionKey.'|'.$key->hash; if(isset($this->records[$i])) $this->records[$i]['status']='failed'; }
}
