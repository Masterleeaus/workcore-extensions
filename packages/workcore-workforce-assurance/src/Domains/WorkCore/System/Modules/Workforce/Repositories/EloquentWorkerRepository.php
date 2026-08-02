<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Repositories;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Workforce\Contracts\WorkerRepositoryContract;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
final class EloquentWorkerRepository extends TenantScopedRepository implements WorkerRepositoryContract
{
    public function __construct(ConnectionInterface $db,TenantContextContract $tenant,private CompanyScopedReferenceValidator $references){parent::__construct($db,$tenant);}
    public function search(int $companyId,?string $query=null,?string $status=null,?string $skillPublicId=null,int $perPage=25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $q=$this->db->table('tz_workers as workers')->leftJoin('users','users.id','=','workers.user_id')->where('workers.company_id',$companyId)->whereNull('workers.deleted_at');
        $q->when($query,fn(Builder $b)=>$b->where(fn(Builder $n)=>$n->where('workers.display_name','like',"%{$query}%")->orWhere('workers.worker_number','like',"%{$query}%")->orWhere('workers.job_title','like',"%{$query}%")));
        $q->when($status,fn(Builder $b)=>$b->where('workers.employment_status',$status));
        if($skillPublicId){$q->whereExists(function(Builder $s)use($skillPublicId){$s->selectRaw('1')->from('tz_worker_skills as ws')->join('tz_skills as sk','sk.id','=','ws.skill_id')->whereColumn('ws.worker_id','workers.id')->where('sk.public_id',$skillPublicId)->where('sk.company_id',$this->tenant->companyId());});}
        return $q->select(['workers.public_id','workers.worker_number','workers.user_id','workers.worker_type','workers.display_name','workers.job_title','workers.employment_status','workers.timezone','workers.phone','workers.email','workers.hourly_cost','workers.hourly_charge_rate','workers.service_areas','workers.created_at','workers.updated_at'])->orderBy('workers.display_name')->paginate(max(1,min($perPage,100)));
    }
    public function createWorker(array $data,int $companyId,int $actorId): array
    {
        $this->references->requireActiveMember($companyId,(int)$data['user_id'],'user_id');
        if($this->db->table('tz_workers')->where('company_id',$companyId)->where('user_id',(int)$data['user_id'])->whereNull('deleted_at')->exists()) throw new InvalidArgumentException('This company member already has a worker profile.');
        $public=(string)Str::ulid(); $number=trim((string)($data['worker_number']??'')); if($number==='')$number='WRK-'.Str::upper(substr($public,-6));
        $id=$this->db->table('tz_workers')->insertGetId(['public_id'=>$public,'company_id'=>$companyId,'user_id'=>(int)$data['user_id'],'worker_number'=>$number,'worker_type'=>$data['worker_type']??'employee','display_name'=>$data['display_name'],'job_title'=>$data['job_title']??null,'employment_status'=>$data['employment_status']??'active','timezone'=>$data['timezone']??config('workcore.context.default_timezone'),'phone'=>$data['phone']??null,'email'=>$data['email']??null,'hourly_cost'=>$data['hourly_cost']??null,'hourly_charge_rate'=>$data['hourly_charge_rate']??null,'service_areas'=>isset($data['service_areas'])?json_encode($data['service_areas']):null,'metadata'=>isset($data['metadata'])?json_encode($data['metadata']):null,'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
        return (array)$this->db->table('tz_workers')->where('id',$id)->first();
    }
    public function createSkill(array $data,int $companyId,int $actorId): array
    {
        $public=(string)Str::ulid(); $code=Str::slug((string)($data['code']??$data['name']),'_');
        if($this->db->table('tz_skills')->where('company_id',$companyId)->where('code',$code)->whereNull('deleted_at')->exists())throw new InvalidArgumentException('Skill code already exists for this company.');
        $id=$this->db->table('tz_skills')->insertGetId(['public_id'=>$public,'company_id'=>$companyId,'name'=>$data['name'],'code'=>$code,'description'=>$data['description']??null,'category'=>$data['category']??null,'requires_certification'=>(bool)($data['requires_certification']??false),'active'=>(bool)($data['active']??true),'created_at'=>now(),'updated_at'=>now()]);
        return (array)$this->db->table('tz_skills')->where('id',$id)->first();
    }
    public function assignSkill(string $workerPublicId,array $data,int $companyId,int $actorId): array
    {
        $worker=$this->worker($workerPublicId,$companyId); $skill=$this->db->table('tz_skills')->where('company_id',$companyId)->where('public_id',$data['skill_public_id'])->whereNull('deleted_at')->first(); if(!$skill)throw new InvalidArgumentException('Skill does not belong to the active company.');
        $existing=$this->db->table('tz_worker_skills')->where('worker_id',$worker->id)->where('skill_id',$skill->id)->first();
        $values=['company_id'=>$companyId,'worker_id'=>$worker->id,'skill_id'=>$skill->id,'proficiency'=>$data['proficiency']??'competent','years_experience'=>$data['years_experience']??null,'verified'=>(bool)($data['verified']??false),'verified_by_user_id'=>($data['verified']??false)?$actorId:null,'verified_at'=>($data['verified']??false)?now():null,'notes'=>$data['notes']??null,'updated_at'=>now()];
        if($existing){$this->db->table('tz_worker_skills')->where('id',$existing->id)->update($values);$id=$existing->id;}else{$values['public_id']=(string)Str::ulid();$values['created_at']=now();$id=$this->db->table('tz_worker_skills')->insertGetId($values);}
        return (array)$this->db->table('tz_worker_skills')->where('id',$id)->first();
    }
    public function addCertification(string $workerPublicId,array $data,int $companyId,int $actorId): array
    {
        $worker=$this->worker($workerPublicId,$companyId); $skillId=null; if(!empty($data['skill_public_id'])){$skill=$this->db->table('tz_skills')->where('company_id',$companyId)->where('public_id',$data['skill_public_id'])->first(); if(!$skill)throw new InvalidArgumentException('Skill does not belong to active company.');$skillId=$skill->id;}
        $id=$this->db->table('tz_worker_certifications')->insertGetId(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'worker_id'=>$worker->id,'skill_id'=>$skillId,'name'=>$data['name'],'issuer'=>$data['issuer']??null,'credential_number'=>$data['credential_number']??null,'issued_on'=>$data['issued_on']??null,'expires_on'=>$data['expires_on']??null,'status'=>$data['status']??'valid','document_path'=>$data['document_path']??null,'notes'=>$data['notes']??null,'created_at'=>now(),'updated_at'=>now()]);
        return (array)$this->db->table('tz_worker_certifications')->where('id',$id)->first();
    }
    private function worker(string $publicId,int $companyId): object{$w=$this->db->table('tz_workers')->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->first();if(!$w)throw new InvalidArgumentException('Worker does not belong to active company.');return $w;}
}
