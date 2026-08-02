<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Services;

use App\Domains\WorkCore\System\Modules\CRM\Support\CrmNormalizer;
use Illuminate\Database\ConnectionInterface;

final class CrmDuplicateDetector
{
    public function __construct(private ConnectionInterface $db) {}

    public function customerCandidates(int $companyId, ?string $email, ?string $phone): array
    {
        return $this->candidates('tz_customers', $companyId, $email, $phone);
    }

    public function leadCandidates(int $companyId, ?string $email, ?string $phone): array
    {
        return $this->candidates('tz_leads', $companyId, $email, $phone);
    }

    private function candidates(string $table, int $companyId, ?string $email, ?string $phone): array
    {
        $email=CrmNormalizer::email($email); $phone=CrmNormalizer::phone($phone);
        if ($email===null && $phone===null) { return []; }
        return $this->db->table($table)->where('company_id',$companyId)->whereNull('deleted_at')
            ->where(function($q) use($email,$phone): void {
                if ($email!==null) { $q->orWhere('normalized_email',$email); }
                if ($phone!==null) { $q->orWhere('normalized_phone',$phone); }
            })->limit(10)->get(['public_id','name','email','phone'])->map(fn($r)=>(array)$r)->all();
    }
}
