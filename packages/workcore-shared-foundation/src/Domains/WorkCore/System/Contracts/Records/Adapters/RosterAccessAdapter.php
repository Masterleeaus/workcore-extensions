<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\RosterAccessContract;

final class RosterAccessAdapter extends AbstractDatabaseRecordAccess implements RosterAccessContract
{
    protected function table(): string { return 'tz_rosters'; }
    protected function type(): string { return 'roster'; }
    protected function columns(): array { return ['public_id','premises_id','name','roster_type','status','starts_on','ends_on','timezone','notes','published_at','closed_at','created_at','updated_at']; }
}
