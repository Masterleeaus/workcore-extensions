<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\JournalRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalEntry;

final class InMemoryJournalRepository implements JournalRepository
{
    /** @var array<string,JournalEntry> */ public array $entries = [];
    public function post(JournalEntry $entry, ActionContext $context): string { $id='journal-'.(count($this->entries)+1); $this->entries[$id]=$entry; return $id; }
    public function existsForSource(string $companyId, string $sourceType, string $sourceId): bool { foreach($this->entries as $e) if($e->companyId===$companyId&&$e->sourceType===$sourceType&&$e->sourceId===$sourceId)return true; return false; }
}
