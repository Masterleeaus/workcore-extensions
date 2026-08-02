<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentSnapshotRepository;
use App\Domains\WorkCore\System\Modules\Finance\Documents\FinancialDocumentSnapshot;

final class InMemoryDocumentSnapshotRepository implements DocumentSnapshotRepository
{
    /** @var list<FinancialDocumentSnapshot> */ public array $snapshots=[];
    public function store(FinancialDocumentSnapshot $snapshot, AuditActor $actor): void { $this->snapshots[]=$snapshot; }
}
