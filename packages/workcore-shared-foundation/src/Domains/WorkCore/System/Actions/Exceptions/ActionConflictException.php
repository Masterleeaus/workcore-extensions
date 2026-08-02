<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Exceptions;

use Throwable;

final class ActionConflictException extends ActionDispatchException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
