<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrArtifactRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\QrArtifact;
use Illuminate\Support\Facades\DB;

final class DatabaseQrArtifactRepository implements QrArtifactRepository
{
    public function store(QrArtifact $a): void { DB::table('tm_qr_artifacts')->insert(['id'=>$a->id,'company_id'=>$a->companyId,'payment_request_id'=>$a->paymentRequestId,'document_id'=>$a->documentId,'content_type'=>$a->contentType,'payload_hash'=>$a->payloadHash,'expires_at'=>$a->expiresAt,'created_at'=>$a->createdAt,'updated_at'=>$a->createdAt]); }
}
