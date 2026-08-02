<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\SalesPipelineAccessContract;

final class SalesPipelineAccessAdapter extends AbstractDatabaseRecordAccess implements SalesPipelineAccessContract
{
    protected function table(): string { return 'tz_sales_pipelines'; }
    protected function type(): string { return 'sales_pipeline'; }
    protected function columns(): array { return ['public_id', 'key', 'name', 'is_default', 'is_active', 'created_at', 'updated_at']; }
}
