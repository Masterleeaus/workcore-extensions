<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\OpportunityAccessContract;

final class OpportunityAccessAdapter extends AbstractDatabaseRecordAccess implements OpportunityAccessContract
{
    protected function table(): string { return 'tz_opportunities'; }
    protected function type(): string { return 'opportunity'; }
    protected function columns(): array
    {
        return [
            'public_id', 'pipeline_id', 'stage_id', 'lead_id', 'customer_id', 'contact_id', 'owner_user_id',
            'title', 'description', 'amount', 'currency_code', 'status', 'probability', 'position',
            'expected_close_date', 'closed_at', 'lost_reason', 'metadata', 'created_at', 'updated_at',
        ];
    }
}
