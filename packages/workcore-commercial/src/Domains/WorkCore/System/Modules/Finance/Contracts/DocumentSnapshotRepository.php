<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Documents\FinancialDocumentSnapshot;

interface DocumentSnapshotRepository
{
    public function store(FinancialDocumentSnapshot $snapshot, AuditActor $actor): void;
}
