<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

use InvalidArgumentException;

final readonly class AttendanceEvidence
{
    public function __construct(public VerificationMethod $method, public bool $passed, public string $referenceHash)
    {
        if (trim($referenceHash) === '') throw new InvalidArgumentException('Attendance evidence must use a non-empty reference hash.');
    }
}
