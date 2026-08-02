<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Repositories;

use App\Domains\WorkCore\System\Modules\CRM\Contracts\ContactRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\ContactData;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Support\Str;
use RuntimeException;

final class EloquentContactRepository extends TenantScopedRepository implements ContactRepositoryContract
{
    public function create(ContactData $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $customer = $this->db->table('tz_customers')->where('company_id', $companyId)->where('public_id', $data->customerPublicId)->whereNull('deleted_at')->first(['id']);
        if (! $customer) { throw new RuntimeException('Customer does not belong to the active company.'); }

        $publicId = (string) Str::ulid();
        $this->db->transaction(function () use ($data, $companyId, $actorId, $customer, $publicId): void {
            if ($data->isPrimary) {
                $this->db->table('tz_customer_contacts')->where('company_id', $companyId)->where('customer_id', $customer->id)->update(['is_primary' => false, 'updated_at' => now()]);
            }
            $this->db->table('tz_customer_contacts')->insert([
                'public_id'=>$publicId,'company_id'=>$companyId,'customer_id'=>$customer->id,'name'=>$data->name,
                'email'=>$data->email,'normalized_email'=>$data->email,'phone'=>$data->phone,'normalized_phone'=>$data->phone,
                'role'=>$data->role,'is_primary'=>$data->isPrimary,'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        });
        return $this->find($companyId, $publicId) ?? ['public_id'=>$publicId];
    }

    public function setPrimary(int $companyId, string $contactPublicId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $contact = $this->db->table('tz_customer_contacts')->where('company_id',$companyId)->where('public_id',$contactPublicId)->whereNull('deleted_at')->first(['id','customer_id']);
        if (! $contact) { throw new RuntimeException('Contact was not found in the active company.'); }
        $this->db->transaction(function () use ($companyId,$actorId,$contact): void {
            $this->db->table('tz_customer_contacts')->where('company_id',$companyId)->where('customer_id',$contact->customer_id)->update(['is_primary'=>false,'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            $this->db->table('tz_customer_contacts')->where('id',$contact->id)->update(['is_primary'=>true,'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
        });
        return $this->find($companyId, $contactPublicId) ?? [];
    }

    public function listForCustomer(int $companyId, string $customerPublicId): array
    {
        $this->assertCompany($companyId);
        return $this->db->table('tz_customer_contacts as c')->join('tz_customers as u','u.id','=','c.customer_id')
            ->where('c.company_id',$companyId)->where('u.company_id',$companyId)->where('u.public_id',$customerPublicId)
            ->whereNull('c.deleted_at')->orderByDesc('c.is_primary')->orderBy('c.name')
            ->get(['c.public_id','c.name','c.email','c.phone','c.role','c.is_primary'])->map(fn($row)=>(array)$row)->all();
    }

    private function find(int $companyId, string $publicId): ?array
    {
        $row=$this->db->table('tz_customer_contacts')->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')
            ->first(['public_id','name','email','phone','role','is_primary']);
        return $row ? (array)$row : null;
    }
}
