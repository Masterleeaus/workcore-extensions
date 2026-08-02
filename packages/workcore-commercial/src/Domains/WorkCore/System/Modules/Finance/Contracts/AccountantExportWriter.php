<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Exports\AccountantExportPack;

interface AccountantExportWriter { public function write(AccountantExportPack $pack, string $format, ActionContext $context): string; }
