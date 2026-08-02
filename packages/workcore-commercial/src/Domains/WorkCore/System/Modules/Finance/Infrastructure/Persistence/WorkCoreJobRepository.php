<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\JobRepository;
use Illuminate\Support\Facades\DB;

/**
 * Pass 35 Stage B — Finance uses canonical WorkCore Operations work orders (tz_work_orders).
 *
 * Work orders (jobs) are not owned by Finance; this repository projects job snapshots
 * from the canonical Operations table for use in profitability calculations and
 * job-linked invoicing.
 */
final class WorkCoreJobRepository implements JobRepository
{
    public function findForCompany(string $companyId, string $jobId): ?array
    {
        $job = DB::table('tz_work_orders')
            ->where('company_id', (int) $companyId)
            ->where(function ($query) use ($jobId): void {
                $query->where('id', (int) $jobId)
                    ->orWhere('public_id', $jobId);
            })
            ->whereNull('deleted_at')
            ->first();

        if ($job === null) {
            return null;
        }

        return [
            'id' => (string) $job->id,
            'public_id' => $job->public_id,
            'company_id' => (string) $job->company_id,
            'customer_id' => (string) $job->customer_id,
            'premises_id' => $job->premises_id ? (string) $job->premises_id : null,
            'number' => $job->number,
            'title' => $job->title,
            'description' => $job->description,
            'status' => $job->status,
            'priority' => $job->priority,
            'source' => $job->source,
            'due_at' => $job->due_at,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
            'cancelled_at' => $job->cancelled_at,
        ];
    }
}
