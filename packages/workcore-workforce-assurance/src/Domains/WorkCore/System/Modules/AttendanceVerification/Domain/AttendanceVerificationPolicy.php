<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

use InvalidArgumentException;

final readonly class AttendanceVerificationPolicy
{
    /** @param list<VerificationMethod> $requiredMethods */
    public function __construct(
        public string $id,
        public string $companyId,
        public array $requiredMethods,
        public int $minimumFactors,
        public bool $allowOffline,
    ) {
        if (trim($id) === '' || trim($companyId) === '') throw new InvalidArgumentException('Attendance policy and company identity are required.');
        if ($minimumFactors < 0 || $minimumFactors > count(VerificationMethod::cases())) throw new InvalidArgumentException('Minimum factors is invalid.');
        if ($minimumFactors > 0 && $requiredMethods === []) throw new InvalidArgumentException('Verification methods are required when factors are enforced.');
    }

    /** @param list<AttendanceEvidence> $evidence */
    public function accepts(array $evidence): bool
    {
        $passed = [];
        foreach ($evidence as $item) {
            if ($item->method === VerificationMethod::Offline && ! $this->allowOffline) continue;
            if ($item->passed) $passed[$item->method->value] = true;
        }
        foreach ($this->requiredMethods as $required) {
            if (! isset($passed[$required->value])) return false;
        }
        return count($passed) >= $this->minimumFactors;
    }
}
