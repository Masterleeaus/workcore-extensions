<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Collections;

final readonly class CollectionSequenceStep
{
    /** @param list<string> $channels */
    public function __construct(public int $dueDay, public string $code, public string $tone, public array $channels, public bool $approvalRequired=false) {}
    public function toArray(): array { return ['due_day'=>$this->dueDay,'code'=>$this->code,'tone'=>$this->tone,'channels'=>$this->channels,'approval_required'=>$this->approvalRequired]; }
}
