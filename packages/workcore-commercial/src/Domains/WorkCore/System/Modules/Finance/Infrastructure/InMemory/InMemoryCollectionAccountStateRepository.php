<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionAccountState;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionAccountStateRepository;

final class InMemoryCollectionAccountStateRepository implements CollectionAccountStateRepository
{
    /** @var array<string,CollectionAccountState> */ public array $states=[];
    public function put(CollectionAccountState $state): void { $this->states[$state->invoiceId]=$state; }
    public function get(string $companyId, string $invoiceId): ?CollectionAccountState { return $this->states[$invoiceId] ?? null; }
}
