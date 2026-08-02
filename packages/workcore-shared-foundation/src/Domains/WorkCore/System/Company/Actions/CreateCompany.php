<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Company\Actions;

use App\Domains\WorkCore\System\Company\DTOs\CreateCompanyData;
use App\Domains\WorkCore\System\Models\Company;
use App\Domains\WorkCore\System\Models\CompanyMember;
use App\Domains\WorkCore\System\Models\CompanyRole;
use App\Domains\WorkCore\System\Modules\CRM\Services\DefaultSalesPipelineProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateCompany
{
    public function __construct(private DefaultSalesPipelineProvisioner $salesPipelines) {}

    public function execute(CreateCompanyData $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $company = Company::query()->create([
                'name' => trim($data->name),
                'slug' => trim($data->slug),
                'status' => 'active',
                'timezone' => $data->timezone,
                'currency_code' => strtoupper($data->currencyCode),
                'country_code' => strtoupper($data->countryCode),
                'abn' => $data->abn,
                'gst_registered' => $data->gstRegistered,
                'owner_user_id' => $data->ownerUserId,
            ]);

            $roles = [
                ['name' => 'Owner', 'key' => 'owner', 'priority' => 1],
                ['name' => 'Manager', 'key' => 'manager', 'priority' => 20],
                ['name' => 'Dispatcher', 'key' => 'dispatcher', 'priority' => 40],
                ['name' => 'Field Worker', 'key' => 'field_worker', 'priority' => 60],
                ['name' => 'Bookkeeper', 'key' => 'bookkeeper', 'priority' => 70],
                ['name' => 'Member', 'key' => 'member', 'priority' => 100],
            ];

            $ownerRole = null;
            foreach ($roles as $roleData) {
                $role = CompanyRole::query()->create($roleData + ['company_id' => $company->getKey(), 'is_system' => true]);
                if ($role->key === 'owner') { $ownerRole = $role; }
            }

            CompanyMember::query()->create([
                'company_id' => $company->getKey(),
                'user_id' => $data->ownerUserId,
                'role_id' => $ownerRole?->getKey(),
                'role_key' => 'owner',
                'status' => 'active',
                'is_owner' => true,
                'joined_at' => now(),
            ]);

            foreach ([['web', 'Website', 10], ['phone', 'Phone', 20], ['referral', 'Referral', 30], ['chat', 'Chat', 40]] as [$key, $name, $sort]) {
                DB::table('tz_lead_sources')->insert([
                    'public_id' => (string) Str::ulid(), 'company_id' => $company->getKey(), 'key' => $key,
                    'name' => $name, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ([['new', 'New', false, false, 10], ['contacted', 'Contacted', false, false, 20], ['qualified', 'Qualified', false, false, 30], ['won', 'Won', true, true, 90], ['lost', 'Lost', true, false, 100]] as [$key, $name, $closed, $won, $sort]) {
                DB::table('tz_lead_statuses')->insert([
                    'public_id' => (string) Str::ulid(), 'company_id' => $company->getKey(), 'key' => $key,
                    'name' => $name, 'is_closed' => $closed, 'is_won' => $won, 'sort_order' => $sort,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $this->salesPipelines->ensure((int) $company->getKey(), $data->ownerUserId);

            // Active-company selection belongs to the host identity adapter. The caller
            // receives the created company and may switch context after this transaction.

            return $company->fresh(['memberships', 'memberships.role']);
        });
    }
}
