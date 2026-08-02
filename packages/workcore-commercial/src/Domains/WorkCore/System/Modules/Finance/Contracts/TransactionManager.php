<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface TransactionManager
{
    /** @template T @param callable():T $callback @return T */
    public function run(callable $callback): mixed;
}
