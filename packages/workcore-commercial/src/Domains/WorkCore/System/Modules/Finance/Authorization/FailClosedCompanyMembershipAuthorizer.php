<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Authorization;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CompanyMembershipAuthorizer;
use App\Domains\WorkCore\System\Modules\Finance\Tenancy\Exceptions\CompanyMembershipRequiredException;

final class FailClosedCompanyMembershipAuthorizer implements CompanyMembershipAuthorizer
{
    public function assertMembership(ActionContext $context): void
    {
        throw new CompanyMembershipRequiredException(
            'Titan Money requires a Platform Core company-membership adapter before financial actions can execute.',
        );
    }
}
