<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\RecurringServices\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Operations\Services\WorkOrderTemplateExpander;
use App\Domains\WorkCore\System\Modules\RecurringServices\Contracts\RecurringServiceRepositoryContract;
use App\Domains\WorkCore\System\Modules\RecurringServices\Services\RecurrenceCalculator;
use App\Domains\WorkCore\System\Modules\RecurringServices\Services\ServiceAgreementStatusPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentRecurringServiceRepository extends TenantScopedRepository implements RecurringServiceRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private RecurrenceCalculator $calculator,
        private ServiceAgreementStatusPolicy $statusPolicy,
        private WorkOrderTemplateExpander $templateExpander,
    ) { parent::__construct($db, $tenant); }

    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $q = $this->db->table('tz_service_agreements as a')
            ->join('tz_customers as c', 'c.id', '=', 'a.customer_id')
            ->join('tz_services as s', 's.id', '=', 'a.service_id')
            ->leftJoin('tz_premises as p', 'p.id', '=', 'a.premises_id')
            ->where('a.company_id', $companyId)->whereNull('a.deleted_at');
        if (! empty($filters['status'])) $q->where('a.status', $filters['status']);
        if (! empty($filters['agreement_type'])) $q->where('a.agreement_type', $filters['agreement_type']);
        if (! empty($filters['customer_public_id'])) $q->where('c.public_id', $filters['customer_public_id']);
        if (! empty($filters['premises_public_id'])) $q->where('p.public_id', $filters['premises_public_id']);
        if (! empty($filters['service_public_id'])) $q->where('s.public_id', $filters['service_public_id']);
        if (! empty($filters['renewal_due_before'])) $q->whereDate('a.ends_on', '<=', $filters['renewal_due_before']);
        if (! empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $q->where(fn ($x) => $x->where('a.name', 'like', $term)->orWhere('a.agreement_number', 'like', $term)->orWhere('c.name', 'like', $term)->orWhere('p.name', 'like', $term)->orWhere('s.name', 'like', $term));
        }
        return $q->select([
            'a.public_id','a.agreement_number','a.name','a.agreement_type','a.status','a.starts_on','a.ends_on','a.renewal_type','a.renewal_notice_days',
            'a.timezone','a.auto_create_work_orders','a.auto_create_appointments','a.generation_lead_days','a.default_priority','a.default_duration_minutes','a.created_at',
            'c.public_id as customer_public_id','c.name as customer_name','p.public_id as premises_public_id','p.name as premises_name','s.public_id as service_public_id','s.name as service_name',
        ])->orderByRaw("CASE a.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")->orderBy('a.ends_on')->paginate(max(1, min(100, $perPage)));
    }

    public function createAgreement(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $customer = $this->record('tz_customers', $companyId, $d['customer_public_id'] ?? null, true);
        $premises = $this->record('tz_premises', $companyId, $d['premises_public_id'] ?? null, true);
        if (! $customer && $premises?->customer_id) $customer = $this->db->table('tz_customers')->where('company_id', $companyId)->where('id', $premises->customer_id)->whereNull('deleted_at')->first();
        if (! $customer) throw new InvalidArgumentException('A customer or customer-linked premises is required.');
        if ($premises && (int) $premises->customer_id !== (int) $customer->id) throw new InvalidArgumentException('Premises does not belong to the selected customer.');
        $service = $this->record('tz_services', $companyId, $d['service_public_id'] ?? null, true);
        if (! $service) throw new InvalidArgumentException('An active company service is required.');
        $template = $this->record('tz_job_templates', $companyId, $d['job_template_public_id'] ?? null, true);
        if ($template && (int) $template->service_id !== (int) $service->id) throw new InvalidArgumentException('Job template does not belong to the selected service.');
        $rebooking = $this->record('tz_rebooking_requests', $companyId, $d['source_rebooking_request_public_id'] ?? null);
        $rules = (array) ($d['rules'] ?? []);
        if ($rules === []) throw new InvalidArgumentException('At least one recurring-service rule is required.');
        $startsOn = (string) $d['starts_on'];
        $endsOn = $d['ends_on'] ?? null;
        if ($endsOn && strtotime((string) $endsOn) < strtotime($startsOn)) throw new InvalidArgumentException('Agreement end date cannot be before its start date.');
        $publicId = (string) Str::ulid();
        $number = $d['agreement_number'] ?? ('AGR-' . date('Ymd') . '-' . substr($publicId, -6));
        return $this->db->transaction(function () use ($d,$companyId,$actorId,$customer,$premises,$service,$template,$rebooking,$rules,$startsOn,$endsOn,$publicId,$number): array {
            $agreementId = $this->db->table('tz_service_agreements')->insertGetId([
                'public_id'=>$publicId,'company_id'=>$companyId,'customer_id'=>$customer->id,'premises_id'=>$premises?->id,'service_id'=>$service->id,
                'job_template_id'=>$template?->id,'source_rebooking_request_id'=>$rebooking?->id,'agreement_number'=>$number,'name'=>$d['name'],
                'agreement_type'=>$d['agreement_type']??'service','status'=>$d['status']??'draft','starts_on'=>$startsOn,'ends_on'=>$endsOn,
                'renewal_type'=>$d['renewal_type']??'manual','renewal_notice_days'=>$d['renewal_notice_days']??30,
                'timezone'=>$d['timezone']??config('workcore.context.default_timezone'),'auto_create_work_orders'=>(bool)($d['auto_create_work_orders']??true),
                'auto_create_appointments'=>(bool)($d['auto_create_appointments']??true),'generation_lead_days'=>$d['generation_lead_days']??14,
                'default_priority'=>$d['default_priority']??'normal','default_duration_minutes'=>$d['default_duration_minutes']??$service->default_duration_minutes,
                'access_notes'=>$d['access_notes']??null,'notes'=>$d['notes']??null,'metadata'=>$this->json($d['metadata']??null),
                'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach ($rules as $rule) $this->insertRule($agreementId, $companyId, $rule, $startsOn, $endsOn, (string) ($d['timezone'] ?? config('workcore.context.default_timezone')), max(1, (int) ($d['default_duration_minutes'] ?? $service->default_duration_minutes ?? 60)));
            $this->history($companyId,'agreement',$agreementId,null,(string)($d['status']??'draft'),'Agreement created.',$actorId);
            if ($rebooking) $this->db->table('tz_rebooking_requests')->where('id',$rebooking->id)->update(['status'=>'converted_to_recurring','updated_at'=>now()]);
            return (array) $this->db->table('tz_service_agreements')->where('id',$agreementId)->first();
        });
    }

    public function changeAgreementStatus(string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId,$actorId); $a=$this->agreement($publicId,$companyId); $to=(string)$d['status'];
        $this->statusPolicy->assertTransition((string)$a->status,$to,$d['reason']??null);
        $this->db->table('tz_service_agreements')->where('id',$a->id)->update(['status'=>$to,'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
        $this->db->table('tz_recurring_service_rules')->where('agreement_id',$a->id)->whereNull('deleted_at')->update(['status'=>$to==='active'?'active':($to==='paused'?'paused':'inactive'),'updated_at'=>now()]);
        $this->history($companyId,'agreement',(int)$a->id,(string)$a->status,$to,$d['reason']??null,$actorId);
        return (array)$this->db->table('tz_service_agreements')->where('id',$a->id)->first();
    }

    public function generate(?string $agreementPublicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId,$actorId);
        $fromOn=(string)($d['from_on']??date('Y-m-d'));
        $toOn=(string)($d['to_on']??date('Y-m-d',strtotime('+' . (int)config('workcore.recurring.generation_horizon_days',60) . ' days')));
        if (strtotime($toOn)<strtotime($fromOn)) throw new InvalidArgumentException('Generation end date cannot be before start date.');
        $q=$this->db->table('tz_service_agreements')->where('company_id',$companyId)->where('status','active')->whereNull('deleted_at');
        if ($agreementPublicId) $q->where('public_id',$agreementPublicId);
        $agreements=$q->get();
        if ($agreementPublicId && $agreements->isEmpty()) throw new InvalidArgumentException('Active service agreement does not belong to active company.');
        $created=[]; $workOrders=0; $appointments=0;
        foreach($agreements as $agreement){
            $rules=$this->db->table('tz_recurring_service_rules')->where('company_id',$companyId)->where('agreement_id',$agreement->id)->where('status','active')->whereNull('deleted_at')->get();
            foreach($rules as $rule){
                $ruleData=(array)$rule; $ruleData['weekdays']=$this->decode($rule->weekdays);
                $limit=$rule->occurrence_limit?(int)$rule->occurrence_limit:null;
                foreach($this->calculator->between($ruleData,$fromOn,$toOn) as $localStart){
                    if($limit!==null && $this->db->table('tz_recurring_service_occurrences')->where('rule_id',$rule->id)->count()>=$limit) break;
                    $key='r'.$rule->id.':'.$localStart->format('YmdHis');
                    $existing=$this->db->table('tz_recurring_service_occurrences')->where('company_id',$companyId)->where('occurrence_key',$key)->first();
                    $duration=max(1,(int)($rule->duration_minutes?:$agreement->default_duration_minutes?:60));
                    $localEnd=$localStart->add(new DateInterval('PT'.$duration.'M'));
                    $utc=new DateTimeZone('UTC'); $startUtc=$localStart->setTimezone($utc); $endUtc=$localEnd->setTimezone($utc);
                    $leadCutoff=(new DateTimeImmutable('today',new DateTimeZone((string)$rule->timezone)))->add(new DateInterval('P'.max(0,(int)$agreement->generation_lead_days).'D'))->setTime(23,59,59);
                    $operational=(bool)($d['create_operational_records']??true) && ($localStart<=$leadCutoff || !empty($d['force_operational_records']));
                    if($existing){
                        if($operational && $existing->status==='planned'){
                            $promoted=$this->promoteOccurrence($existing,$agreement,$localStart,$startUtc,$endUtc,$companyId,$actorId,$workOrders,$appointments);
                            $created[]=$promoted;
                        }
                        continue;
                    }
                    $row=$this->db->transaction(function()use($companyId,$actorId,$agreement,$rule,$key,$localStart,$startUtc,$endUtc,$operational,&$workOrders,&$appointments):array{
                        $occurrencePublicId=(string)Str::ulid();
                        $occurrenceId=$this->db->table('tz_recurring_service_occurrences')->insertGetId([
                            'public_id'=>$occurrencePublicId,'company_id'=>$companyId,'agreement_id'=>$agreement->id,'rule_id'=>$rule->id,
                            'customer_id'=>$agreement->customer_id,'premises_id'=>$agreement->premises_id,'service_id'=>$agreement->service_id,
                            'occurrence_key'=>$key,'local_date'=>$localStart->format('Y-m-d'),'scheduled_start_at'=>$startUtc->format('Y-m-d H:i:s'),
                            'scheduled_end_at'=>$endUtc->format('Y-m-d H:i:s'),'timezone'=>$rule->timezone,'status'=>'planned','created_by_user_id'=>$actorId,
                            'created_at'=>now(),'updated_at'=>now(),
                        ]);
                        $workOrderId=null; $appointmentId=null;
                        if($operational && $agreement->auto_create_work_orders){$workOrderId=$this->createWorkOrder($agreement,$occurrenceId,$occurrencePublicId,$startUtc,$companyId,$actorId);$workOrders++;}
                        if($operational && $agreement->auto_create_appointments){$appointmentId=$this->createAppointment($agreement,$workOrderId,$localStart,$startUtc,$endUtc,$companyId,$actorId);$appointments++;}
                        $status=($workOrderId||$appointmentId)?'generated':'planned';
                        $this->db->table('tz_recurring_service_occurrences')->where('id',$occurrenceId)->update(['work_order_id'=>$workOrderId,'appointment_id'=>$appointmentId,'status'=>$status,'generated_at'=>$status==='generated'?now():null,'updated_at'=>now()]);
                        return (array)$this->db->table('tz_recurring_service_occurrences')->where('id',$occurrenceId)->first();
                    });
                    $created[]=$row;
                }
                $next=date('Y-m-d',strtotime($toOn.' +1 day'));
                $this->db->table('tz_recurring_service_rules')->where('id',$rule->id)->update(['last_generated_on'=>$toOn,'next_run_on'=>$next,'generated_count'=>$this->db->table('tz_recurring_service_occurrences')->where('rule_id',$rule->id)->count(),'updated_at'=>now()]);
            }
        }
        return ['public_id'=>(string)Str::ulid(),'from_on'=>$fromOn,'to_on'=>$toOn,'agreements_processed'=>$agreements->count(),'occurrences_created'=>count($created),'work_orders_created'=>$workOrders,'appointments_created'=>$appointments,'occurrences'=>$created];
    }

    public function skipOccurrence(string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId,$actorId); $o=$this->occurrence($publicId,$companyId); $reason=trim((string)($d['reason']??''));
        if($reason==='') throw new InvalidArgumentException('A reason is required when skipping a recurring occurrence.');
        if(in_array($o->status,['completed','skipped','cancelled'],true)) throw new InvalidArgumentException('This occurrence can no longer be skipped.');
        if($o->work_order_id && $this->db->table('tz_work_orders')->where('id',$o->work_order_id)->value('status')==='completed') throw new InvalidArgumentException('A completed linked Work Order prevents skipping this occurrence.');
        if($o->appointment_id && $this->db->table('tz_appointments')->where('id',$o->appointment_id)->value('status')==='completed') throw new InvalidArgumentException('A completed linked Appointment prevents skipping this occurrence.');
        return $this->db->transaction(function()use($o,$d,$companyId,$actorId,$reason):array{
            if(($o->work_order_id||$o->appointment_id)&&empty($d['cancel_linked_records'])) throw new InvalidArgumentException('Generated occurrences require cancel_linked_records=true before they can be skipped.');
            if($o->work_order_id){$wo=$this->db->table('tz_work_orders')->where('id',$o->work_order_id)->first();if($wo&&!in_array($wo->status,['completed','cancelled'],true)){$this->db->table('tz_work_orders')->where('id',$wo->id)->update(['status'=>'cancelled','cancelled_at'=>now(),'updated_by_user_id'=>$actorId,'updated_at'=>now()]);$this->db->table('tz_work_order_status_history')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'work_order_id'=>$wo->id,'from_status'=>$wo->status,'to_status'=>'cancelled','reason'=>$reason,'changed_by_user_id'=>$actorId,'changed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);}}
            if($o->appointment_id){$ap=$this->db->table('tz_appointments')->where('id',$o->appointment_id)->first();if($ap&&!in_array($ap->status,['completed','cancelled'],true)){$this->db->table('tz_appointments')->where('id',$ap->id)->update(['status'=>'cancelled','updated_by_user_id'=>$actorId,'updated_at'=>now()]);$this->db->table('tz_appointment_status_history')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'appointment_id'=>$ap->id,'from_status'=>$ap->status,'to_status'=>'cancelled','reason'=>$reason,'changed_by_user_id'=>$actorId,'changed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);}}
            $this->db->table('tz_recurring_service_occurrences')->where('id',$o->id)->update(['status'=>'skipped','exception_reason'=>$reason,'updated_at'=>now()]);
            $this->insertException($o,'skip',null,null,$reason,$companyId,$actorId);$this->history($companyId,'occurrence',(int)$o->id,(string)$o->status,'skipped',$reason,$actorId);
            return (array)$this->db->table('tz_recurring_service_occurrences')->where('id',$o->id)->first();
        });
    }

    public function rescheduleOccurrence(string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId,$actorId); $o=$this->occurrence($publicId,$companyId); $reason=trim((string)($d['reason']??''));
        if($reason==='') throw new InvalidArgumentException('A reason is required when rescheduling a recurring occurrence.');
        if(in_array($o->status,['completed','skipped','cancelled'],true)) throw new InvalidArgumentException('This occurrence can no longer be rescheduled.');
        if($o->work_order_id && $this->db->table('tz_work_orders')->where('id',$o->work_order_id)->value('status')==='completed') throw new InvalidArgumentException('A completed linked Work Order prevents rescheduling this occurrence.');
        if($o->appointment_id && $this->db->table('tz_appointments')->where('id',$o->appointment_id)->value('status')==='completed') throw new InvalidArgumentException('A completed linked Appointment prevents rescheduling this occurrence.');
        $timezone=new DateTimeZone((string)($d['timezone']??$o->timezone));
        $start=(new DateTimeImmutable((string)$d['starts_at'],$timezone))->setTimezone(new DateTimeZone('UTC'));
        $end=(new DateTimeImmutable((string)$d['ends_at'],$timezone))->setTimezone(new DateTimeZone('UTC'));
        if($end<=$start) throw new InvalidArgumentException('Rescheduled end time must be after start time.');
        return $this->db->transaction(function()use($o,$d,$companyId,$actorId,$reason,$start,$end,$timezone):array{
            if($o->work_order_id)$this->db->table('tz_work_orders')->where('id',$o->work_order_id)->update(['due_at'=>$start->format('Y-m-d H:i:s'),'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            if($o->appointment_id)$this->db->table('tz_appointments')->where('id',$o->appointment_id)->update(['starts_at'=>$start->format('Y-m-d H:i:s'),'ends_at'=>$end->format('Y-m-d H:i:s'),'timezone'=>$timezone->getName(),'updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            $this->insertException($o,'reschedule',$start,$end,$reason,$companyId,$actorId);
            $this->db->table('tz_recurring_service_occurrences')->where('id',$o->id)->update(['scheduled_start_at'=>$start->format('Y-m-d H:i:s'),'scheduled_end_at'=>$end->format('Y-m-d H:i:s'),'timezone'=>$timezone->getName(),'local_date'=>$start->setTimezone($timezone)->format('Y-m-d'),'status'=>'rescheduled','exception_reason'=>$reason,'updated_at'=>now()]);
            $this->history($companyId,'occurrence',(int)$o->id,(string)$o->status,'rescheduled',$reason,$actorId);
            return (array)$this->db->table('tz_recurring_service_occurrences')->where('id',$o->id)->first();
        });
    }

    public function renewAgreement(string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId,$actorId); $a=$this->agreement($publicId,$companyId); $newEnd=(string)$d['new_ends_on'];
        $this->statusPolicy->assertRenewal((string)$a->status,$a->ends_on?:(null),$newEnd);
        $reason=trim((string)($d['reason']??'Agreement renewed.'));
        return $this->db->transaction(function()use($a,$newEnd,$reason,$companyId,$actorId):array{
            $from=(string)$a->status;
            $this->db->table('tz_service_agreements')->where('id',$a->id)->update(['ends_on'=>$newEnd,'status'=>'active','updated_by_user_id'=>$actorId,'updated_at'=>now()]);
            $this->db->table('tz_recurring_service_rules')->where('agreement_id',$a->id)->whereNull('deleted_at')->update(['ends_on'=>$newEnd,'status'=>'active','updated_at'=>now()]);
            $this->history($companyId,'agreement',(int)$a->id,$from,'active',$reason,$actorId);
            return (array)$this->db->table('tz_service_agreements')->where('id',$a->id)->first();
        });
    }

    private function promoteOccurrence(object $occurrence,object $agreement,DateTimeImmutable $localStart,DateTimeImmutable $startUtc,DateTimeImmutable $endUtc,int $companyId,int $actorId,int &$workOrders,int &$appointments): array
    {
        return $this->db->transaction(function()use($occurrence,$agreement,$localStart,$startUtc,$endUtc,$companyId,$actorId,&$workOrders,&$appointments):array{
            $workOrderId=$occurrence->work_order_id;$appointmentId=$occurrence->appointment_id;
            if(!$workOrderId && $agreement->auto_create_work_orders){$workOrderId=$this->createWorkOrder($agreement,(int)$occurrence->id,(string)$occurrence->public_id,$startUtc,$companyId,$actorId);$workOrders++;}
            if(!$appointmentId && $agreement->auto_create_appointments){$appointmentId=$this->createAppointment($agreement,$workOrderId,$localStart,$startUtc,$endUtc,$companyId,$actorId);$appointments++;}
            $status=($workOrderId||$appointmentId)?'generated':'planned';
            $this->db->table('tz_recurring_service_occurrences')->where('id',$occurrence->id)->update(['work_order_id'=>$workOrderId,'appointment_id'=>$appointmentId,'status'=>$status,'generated_at'=>$status==='generated'?now():null,'updated_at'=>now()]);
            return (array)$this->db->table('tz_recurring_service_occurrences')->where('id',$occurrence->id)->first();
        });
    }

    private function createWorkOrder(object $agreement,int $occurrenceId,string $occurrencePublicId,DateTimeImmutable $startUtc,int $companyId,int $actorId): int
    {
        $publicId=(string)Str::ulid();$number='REC-'.$startUtc->format('Ymd').'-'.substr($publicId,-6);
        $id=$this->db->table('tz_work_orders')->insertGetId(['public_id'=>$publicId,'company_id'=>$companyId,'customer_id'=>$agreement->customer_id,'premises_id'=>$agreement->premises_id,'number'=>$number,'title'=>$agreement->name,'description'=>'Generated from recurring service agreement '.$agreement->agreement_number.'. Occurrence '.$occurrencePublicId.'.','priority'=>$agreement->default_priority,'status'=>'ready','source'=>'recurring_service','due_at'=>$startUtc->format('Y-m-d H:i:s'),'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
        $service=$this->db->table('tz_services')->where('id',$agreement->service_id)->first();$template=$agreement->job_template_id?$this->db->table('tz_job_templates')->where('id',$agreement->job_template_id)->first():null;
        $this->templateExpander->expand($companyId,$id,$actorId,['service_public_id'=>$service->public_id,'job_template_public_id'=>$template?->public_id,'estimated_duration_minutes'=>$agreement->default_duration_minutes],0);
        $this->db->table('tz_work_order_status_history')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'work_order_id'=>$id,'from_status'=>null,'to_status'=>'ready','reason'=>'Automatically generated from recurring service agreement.','changed_by_user_id'=>$actorId,'changed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }

    private function createAppointment(object $agreement,?int $workOrderId,DateTimeImmutable $localStart,DateTimeImmutable $startUtc,DateTimeImmutable $endUtc,int $companyId,int $actorId): int
    {
        $id=$this->db->table('tz_appointments')->insertGetId(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'work_order_id'=>$workOrderId,'customer_id'=>$agreement->customer_id,'premises_id'=>$agreement->premises_id,'title'=>$agreement->name,'description'=>'Automatically generated from recurring service agreement '.$agreement->agreement_number.'.','appointment_type'=>$agreement->agreement_type,'status'=>'scheduled','starts_at'=>$startUtc->format('Y-m-d H:i:s'),'ends_at'=>$endUtc->format('Y-m-d H:i:s'),'timezone'=>$localStart->getTimezone()->getName(),'access_notes'=>$agreement->access_notes,'source'=>'recurring_service','created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
        $this->db->table('tz_appointment_status_history')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'appointment_id'=>$id,'from_status'=>null,'to_status'=>'scheduled','reason'=>'Automatically generated from recurring service agreement.','changed_by_user_id'=>$actorId,'changed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return $id;
    }

    private function insertRule(int $agreementId,int $companyId,array $r,string $agreementStart,?string $agreementEnd,string $defaultTimezone,int $defaultDuration): void
    {
        $frequency=(string)($r['frequency']??'monthly');
        if(!in_array($frequency,['daily','weekly','monthly','quarterly','yearly'],true))throw new InvalidArgumentException('Unsupported recurring-service frequency.');
        $starts=(string)($r['starts_on']??$agreementStart);$ends=$r['ends_on']??$agreementEnd;if($ends&&strtotime((string)$ends)<strtotime($starts))throw new InvalidArgumentException('Recurring rule end date cannot be before start date.');
        $this->db->table('tz_recurring_service_rules')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'agreement_id'=>$agreementId,'frequency'=>$frequency,'interval_count'=>max(1,(int)($r['interval_count']??1)),'weekdays'=>$this->json($r['weekdays']??null),'day_of_month'=>$r['day_of_month']??null,'month_of_year'=>$r['month_of_year']??null,'time_of_day'=>$r['time_of_day']??'09:00:00','duration_minutes'=>$r['duration_minutes']??$defaultDuration,'timezone'=>$r['timezone']??$defaultTimezone,'starts_on'=>$starts,'ends_on'=>$ends,'next_run_on'=>$starts,'occurrence_limit'=>$r['occurrence_limit']??null,'status'=>'active','metadata'=>$this->json($r['metadata']??null),'created_at'=>now(),'updated_at'=>now()]);
    }

    private function insertException(object $o,string $type,?DateTimeImmutable $replacementStart,?DateTimeImmutable $replacementEnd,string $reason,int $companyId,int $actorId): void
    {
        $this->db->table('tz_recurring_service_exceptions')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'agreement_id'=>$o->agreement_id,'rule_id'=>$o->rule_id,'occurrence_id'=>$o->id,'exception_type'=>$type,'original_start_at'=>$o->scheduled_start_at,'replacement_start_at'=>$replacementStart?->format('Y-m-d H:i:s'),'replacement_end_at'=>$replacementEnd?->format('Y-m-d H:i:s'),'reason'=>$reason,'actor_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function agreement(string $publicId,int $companyId): object { $a=$this->db->table('tz_service_agreements')->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->first();if(!$a)throw new InvalidArgumentException('Service agreement does not belong to active company.');return $a; }
    private function occurrence(string $publicId,int $companyId): object { $o=$this->db->table('tz_recurring_service_occurrences')->where('company_id',$companyId)->where('public_id',$publicId)->first();if(!$o)throw new InvalidArgumentException('Recurring-service occurrence does not belong to active company.');return $o; }
    private function record(string $table,int $companyId,mixed $publicId,bool $soft=false): ?object { if(!$publicId)return null;$q=$this->db->table($table)->where('company_id',$companyId)->where('public_id',(string)$publicId);if($soft)$q->whereNull('deleted_at');$r=$q->first();if(!$r)throw new InvalidArgumentException("Referenced {$table} record does not belong to active company.");return $r; }
    private function actor(int $companyId,int $actorId): void { $this->assertCompany($companyId);if($actorId<1||$actorId!==$this->actorId())throw new InvalidArgumentException('Recurring-service action actor does not match active user.'); }
    private function history(int $companyId,string $type,int $id,?string $from,string $to,?string $reason,int $actorId): void { $this->db->table('tz_recurring_service_status_history')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'subject_type'=>$type,'subject_id'=>$id,'from_status'=>$from,'to_status'=>$to,'reason'=>$reason,'actor_user_id'=>$actorId,'changed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]); }
    private function json(mixed $v): ?string { return $v===null?null:json_encode($v,JSON_THROW_ON_ERROR); }
    private function decode(mixed $v): array { if(is_array($v))return $v;if(!$v)return [];return (array)json_decode((string)$v,true,512,JSON_THROW_ON_ERROR); }
}
