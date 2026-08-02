<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\PremisesAccessContract;
final class PremisesAccessAdapter extends AbstractDatabaseRecordAccess implements PremisesAccessContract
{
    protected function table(): string{return 'tz_premises';}
    protected function type(): string{return 'premises';}
    protected function columns(): array{return ['public_id','customer_id','name','address_line_1','address_line_2','suburb','state','postcode','country_code','latitude','longitude','access_instructions','parking_instructions','hazards','service_requirements','is_primary','status'];}
}
