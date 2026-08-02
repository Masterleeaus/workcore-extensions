<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ActionDispatchException extends HttpException
{
    public function __construct(string $message, int $statusCode = 422, ?Throwable $previous = null)
    {
        parent::__construct($statusCode, $message, $previous);
    }
}
