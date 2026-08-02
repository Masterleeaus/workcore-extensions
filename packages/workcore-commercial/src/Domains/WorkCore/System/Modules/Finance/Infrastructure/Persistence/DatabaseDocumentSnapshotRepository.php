<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentSnapshotRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Documents\FinancialDocumentSnapshot;
use Illuminate\Support\Facades\DB;

final class DatabaseDocumentSnapshotRepository implements DocumentSnapshotRepository
{
    public function __construct(private readonly IdentifierGenerator $ids) {}
    public function store(FinancialDocumentSnapshot $s,AuditActor $actor): void { DB::table('tm_document_snapshots')->insert(['id'=>$this->ids->uuid(),'company_id'=>$s->companyId,'document_type'=>$s->documentType,'document_id'=>$s->documentId,'version'=>$s->version,'payload'=>json_encode($s->payload,JSON_THROW_ON_ERROR),'payload_hash'=>$s->payloadHash,
        'created_by_user_id'=>$actor->type->value==='user'?$actor->id:null,'created_by_actor_type'=>$actor->type->value,'created_by_actor_id'=>$actor->id,'updated_by_actor_type'=>$actor->type->value,'updated_by_actor_id'=>$actor->id,'captured_at'=>$s->capturedAt,'created_at'=>now(),'updated_at'=>now()]); }
}
