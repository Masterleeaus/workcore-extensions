<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\CustomerRepository;
use Illuminate\Support\Facades\DB;

/**
 * Pass 35 Stage B — Finance uses canonical WorkCore CRM customers (tz_customers).
 *
 * Customers are not owned by Finance; this repository projects customer snapshots
 * from the canonical CRM table for use in invoice drafts and payment records.
 */
final class WorkCoreCustomerRepository implements CustomerRepository
{
    public function findForCompany(string $companyId, string $customerId): ?array
    {
        $customer = DB::table('tz_customers')
            ->where('company_id', (int) $companyId)
            ->where(function ($query) use ($customerId): void {
                $query->where('id', (int) $customerId)
                    ->orWhere('public_id', $customerId);
            })
            ->whereNull('deleted_at')
            ->first();

        if ($customer === null) {
            return null;
        }

        return [
            'id' => (string) $customer->id,
            'public_id' => $customer->public_id,
            'company_id' => (string) $customer->company_id,
            'name' => $customer->name,
            'legal_name' => $customer->legal_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'notes' => $customer->notes,
        ];
    }
}
