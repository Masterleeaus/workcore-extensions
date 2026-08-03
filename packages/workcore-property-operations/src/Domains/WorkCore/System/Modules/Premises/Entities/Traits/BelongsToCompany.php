<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Traits;

/**
 * Backward-compatible namespace wrapper around WorkCore's canonical,
 * fail-closed tenant boundary.
 */
trait BelongsToCompany
{
    use \App\Domains\WorkCore\System\Tenancy\BelongsToCompany;
}
