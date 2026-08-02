<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\CompanyMemberAccessContract;
final class CompanyMemberAccessAdapter extends AbstractDatabaseRecordAccess implements CompanyMemberAccessContract
{
    protected function table(): string{return 'tz_company_memberships';}
    protected function type(): string{return 'company_member';}
    protected function columns(): array{return ['public_id','user_id','role_key','status','is_owner'];}
}
