<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalEntry;

interface JournalRepository { public function post(JournalEntry $entry, ActionContext $context): string; public function existsForSource(string $companyId, string $sourceType, string $sourceId): bool; }
