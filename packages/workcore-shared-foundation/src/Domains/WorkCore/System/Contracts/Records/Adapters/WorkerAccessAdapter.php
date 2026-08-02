<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\WorkerAccessContract;
final class WorkerAccessAdapter extends AbstractDatabaseRecordAccess implements WorkerAccessContract
{
 protected function table(): string{return 'tz_workers';} protected function type(): string{return 'worker';}
 protected function columns(): array{return ['public_id','user_id','worker_number','worker_type','display_name','job_title','employment_status','timezone','phone','email','service_areas','created_at','updated_at'];}
}
