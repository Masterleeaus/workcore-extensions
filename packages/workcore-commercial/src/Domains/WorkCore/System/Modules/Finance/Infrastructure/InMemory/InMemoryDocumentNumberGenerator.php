<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentNumberGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceKey;
use DateTimeImmutable;

final class InMemoryDocumentNumberGenerator implements DocumentNumberGenerator
{
    /** @var array<string,int> */ private array $next = [];
    public function next(string $companyId, SequenceKey $sequence, DateTimeImmutable $effectiveAt): string
    {
        $key=$companyId.'|'.$sequence->value; $value=$this->next[$key]??1; $this->next[$key]=$value+1;
        return strtoupper(substr($sequence->value,0,3)).'-'.str_pad((string)$value,6,'0',STR_PAD_LEFT);
    }
}
