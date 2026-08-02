<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Wizards\Repositories;

use App\Domains\WorkCore\System\Modules\Wizards\Contracts\WizardRepositoryContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentWizardRepository implements WizardRepositoryContract
{
    public function __construct(private ConnectionInterface $db) {}
    public function createDefinition(array $definition, int $companyId, int $actorId): array
    {
        $publicId=(string)Str::ulid(); $key=(string)$definition['key']; $version=(int)($definition['version']??1);
        $this->db->table('tz_wizard_definitions')->insert(['public_id'=>$publicId,'company_id'=>$companyId,'definition_key'=>$key,'version'=>$version,'title'=>(string)$definition['title'],'description'=>$definition['description']??null,'status'=>'draft','definition'=>json_encode($definition,JSON_THROW_ON_ERROR),'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
        return ['public_id'=>$publicId,'key'=>$key,'version'=>$version,'status'=>'draft'];
    }
    public function publishDefinition(string $publicId, int $companyId, int $actorId): array
    {
        $n=$this->db->table('tz_wizard_definitions')->where('company_id',$companyId)->where('public_id',$publicId)->update(['status'=>'published','published_at'=>now(),'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
        if($n!==1) throw new InvalidArgumentException('Wizard definition does not belong to the active company.');
        return ['public_id'=>$publicId,'status'=>'published'];
    }
    public function startRun(array $definition, array $data, int $companyId, int $actorId): array
    {
        $publicId=(string)Str::ulid(); $first=$definition['sections'][0]['key']??null;
        $this->db->table('tz_wizard_runs')->insert(['public_id'=>$publicId,'company_id'=>$companyId,'definition_key'=>$definition['key'],'definition_version'=>$definition['version']??1,'status'=>'in_progress','mode'=>$data['mode']??'hybrid','current_section_key'=>$first,'initiated_by_user_id'=>$actorId,'assisted_by_agent_public_id'=>$data['agent_public_id']??null,'completion_percent'=>0,'metadata'=>isset($data['metadata'])?json_encode($data['metadata'],JSON_THROW_ON_ERROR):null,'started_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->event($companyId,$publicId,'wizard.run.started',['definition_key'=>$definition['key'],'mode'=>$data['mode']??'hybrid'],'user',(string)$actorId);
        return ['public_id'=>$publicId,'definition_key'=>$definition['key'],'status'=>'in_progress','mode'=>$data['mode']??'hybrid'];
    }
    public function saveAnswer(string $runPublicId, array $question, mixed $value, array $provenance, int $companyId, int $actorId): array
    {
        $run=$this->runRow($runPublicId,$companyId); $existing=$this->db->table('tz_wizard_answers')->where('run_id',$run->id)->where('question_key',$question['key'])->first();
        $data=['company_id'=>$companyId,'run_id'=>$run->id,'question_key'=>$question['key'],'value'=>json_encode($value,JSON_THROW_ON_ERROR),'source'=>$provenance['source']??'user_entered','confidence'=>$provenance['confidence']??null,'is_confirmed'=>(bool)($provenance['is_confirmed'] ?? (($provenance['source'] ?? 'user_entered') === 'user_entered')),'answered_by_user_id'=>$actorId,'answered_by_agent_public_id'=>$provenance['agent_public_id']??null,'updated_at'=>now()];
        if($existing){$this->db->table('tz_wizard_answers')->where('id',$existing->id)->update($data);$publicId=$existing->public_id;}else{$publicId=(string)Str::ulid();$this->db->table('tz_wizard_answers')->insert(['public_id'=>$publicId,'created_at'=>now()]+$data);}
        $this->event($companyId,$runPublicId,'wizard.answer.saved',['question_key'=>$question['key'],'source'=>$data['source'],'confirmed'=>$data['is_confirmed']],$data['answered_by_agent_public_id']?'ai':'user',$data['answered_by_agent_public_id']??(string)$actorId);
        return ['public_id'=>$publicId,'run_public_id'=>$runPublicId,'question_key'=>$question['key'],'source'=>$data['source'],'is_confirmed'=>$data['is_confirmed']];
    }
    public function approveSection(string $runPublicId, string $sectionKey, array $summary, int $companyId, int $actorId): array
    {
        $run=$this->runRow($runPublicId,$companyId); $existing=$this->db->table('tz_wizard_section_approvals')->where('run_id',$run->id)->where('section_key',$sectionKey)->first();
        $data=['company_id'=>$companyId,'run_id'=>$run->id,'section_key'=>$sectionKey,'status'=>'approved','summary'=>json_encode($summary,JSON_THROW_ON_ERROR),'approved_by_user_id'=>$actorId,'approved_at'=>now(),'updated_at'=>now()];
        if($existing)$this->db->table('tz_wizard_section_approvals')->where('id',$existing->id)->update($data);else$this->db->table('tz_wizard_section_approvals')->insert(['public_id'=>(string)Str::ulid(),'created_at'=>now()]+$data);
        $this->event($companyId,$runPublicId,'wizard.section.approved',['section_key'=>$sectionKey],'user',(string)$actorId);
        return ['run_public_id'=>$runPublicId,'section_key'=>$sectionKey,'status'=>'approved'];
    }
    public function changeStatus(string $runPublicId, string $status, int $companyId, int $actorId): array
    {
        $updates=['status'=>$status,'updated_at'=>now()]; if($status==='paused')$updates['paused_at']=now(); if($status==='in_progress')$updates['paused_at']=null; if($status==='completed'){$updates['completed_at']=now();$updates['completion_percent']=100;}
        $n=$this->db->table('tz_wizard_runs')->where('company_id',$companyId)->where('public_id',$runPublicId)->update($updates); if($n!==1)throw new InvalidArgumentException('Wizard run does not belong to the active company.');
        $this->event($companyId,$runPublicId,'wizard.run.'.$status,[],'user',(string)$actorId); return ['public_id'=>$runPublicId,'status'=>$status];
    }
    public function run(string $runPublicId, int $companyId): array { return (array)$this->runRow($runPublicId,$companyId); }
    public function answers(string $runPublicId, int $companyId): array
    {
        $run=$this->runRow($runPublicId,$companyId); $rows=$this->db->table('tz_wizard_answers')->where('company_id',$companyId)->where('run_id',$run->id)->get(); $out=[]; foreach($rows as $r)$out[$r->question_key]=json_decode((string)$r->value,true); return $out;
    }
    public function approvedSections(string $runPublicId, int $companyId): array
    {
        $run=$this->runRow($runPublicId,$companyId); return $this->db->table('tz_wizard_section_approvals')->where('company_id',$companyId)->where('run_id',$run->id)->where('status','approved')->pluck('section_key')->map(static fn($v)=>(string)$v)->all();
    }
    private function runRow(string $publicId,int $companyId): object { $r=$this->db->table('tz_wizard_runs')->where('company_id',$companyId)->where('public_id',$publicId)->first(); if(!$r)throw new InvalidArgumentException('Wizard run does not belong to the active company.'); return $r; }
    private function event(int $companyId,string $runPublicId,string $type,array $payload,string $actorType,?string $actorPublicId): void { $run=$this->db->table('tz_wizard_runs')->where('company_id',$companyId)->where('public_id',$runPublicId)->first(['id']); if(!$run)return; $this->db->table('tz_wizard_events')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'run_id'=>$run->id,'event_type'=>$type,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'actor_type'=>$actorType,'actor_public_id'=>$actorPublicId,'created_at'=>now()]); }
}
