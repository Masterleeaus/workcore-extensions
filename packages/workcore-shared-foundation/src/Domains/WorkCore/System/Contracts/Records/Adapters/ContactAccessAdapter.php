<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\ContactAccessContract;
final class ContactAccessAdapter extends AbstractDatabaseRecordAccess implements ContactAccessContract
{
    protected function table(): string{return 'tz_customer_contacts';}
    protected function type(): string{return 'contact';}
    protected function columns(): array{return ['public_id','name','email','phone','role','is_primary'];}
}
