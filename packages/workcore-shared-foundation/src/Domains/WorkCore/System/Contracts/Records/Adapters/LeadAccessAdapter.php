<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\LeadAccessContract;
final class LeadAccessAdapter extends AbstractDatabaseRecordAccess implements LeadAccessContract
{
    protected function table(): string{return 'tz_leads';}
    protected function type(): string{return 'lead';}
    protected function columns(): array{return ['public_id','name','company_name','email','phone','notes','owner_user_id','priority','next_follow_up'];}
}
