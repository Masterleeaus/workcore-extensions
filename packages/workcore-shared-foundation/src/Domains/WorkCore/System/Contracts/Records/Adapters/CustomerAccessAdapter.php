<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\CustomerAccessContract;
final class CustomerAccessAdapter extends AbstractDatabaseRecordAccess implements CustomerAccessContract
{
    protected function table(): string{return 'tz_customers';}
    protected function type(): string{return 'customer';}
    protected function columns(): array{return ['public_id','name','legal_name','email','phone','address','notes'];}
}
