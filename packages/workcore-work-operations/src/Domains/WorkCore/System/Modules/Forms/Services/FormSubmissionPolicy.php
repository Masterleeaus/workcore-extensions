<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Forms\Services;

use InvalidArgumentException;

final class FormSubmissionPolicy
{
    public function assertEditable(string $status): void
    {
        if (! in_array($status, ['draft','in_progress'], true)) {
            throw new InvalidArgumentException('Only draft or in-progress form submissions can be edited.');
        }
    }

    public function assertTemplatePublishable(int $fieldCount, string $status): void
    {
        if ($status === 'published') {
            return;
        }
        if ($fieldCount < 1) {
            throw new InvalidArgumentException('A form template requires at least one field before publication.');
        }
        if ($status !== 'draft') {
            throw new InvalidArgumentException('Only draft form templates can be published.');
        }
    }

    public function outcome(int $failures, int $required, int $answered): string
    {
        if ($answered < $required) {
            return 'incomplete';
        }
        return $failures > 0 ? 'issues_found' : 'passed';
    }
}
