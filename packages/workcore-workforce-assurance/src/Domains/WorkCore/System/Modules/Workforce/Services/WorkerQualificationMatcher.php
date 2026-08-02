<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Services;
use Illuminate\Database\ConnectionInterface;
final class WorkerQualificationMatcher
{
    public function __construct(private ConnectionInterface $db) {}
    /** @param list<string> $requiredSkillPublicIds @return list<array<string,mixed>> */
    public function qualifiedWorkers(int $companyId, array $requiredSkillPublicIds, bool $requireVerified=true): array
    {
        if ($requiredSkillPublicIds === []) return [];
        $skills=$this->db->table('tz_skills')->where('company_id',$companyId)->whereIn('public_id',$requiredSkillPublicIds)->where('active',true)->whereNull('deleted_at')->get();
        if ($skills->count() !== count(array_unique($requiredSkillPublicIds))) return [];
        $ids=$skills->pluck('id')->map(fn($id)=>(int)$id)->all();
        $q=$this->db->table('tz_workers as w')->join('tz_worker_skills as ws','ws.worker_id','=','w.id')->where('w.company_id',$companyId)->where('w.employment_status','active')->whereNull('w.deleted_at')->whereIn('ws.skill_id',$ids);
        if($requireVerified)$q->where('ws.verified',true);
        return $q->groupBy('w.id','w.public_id','w.user_id','w.display_name')->havingRaw('COUNT(DISTINCT ws.skill_id) = ?', [count($ids)])->select(['w.public_id','w.user_id','w.display_name'])->get()->map(fn($r)=>(array)$r)->all();
    }
}
