<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\{BusinessRecordSnapshot,CompanyAccessContract};
final class CompanyAccessAdapter extends AbstractDatabaseRecordAccess implements CompanyAccessContract
{
    protected function table(): string{return 'tz_companies';}
    protected function type(): string{return 'company';}
    protected function columns(): array{return ['public_id','name','status','locale','timezone','currency_code','country_code'];}
    protected function baseQuery(int $companyId): \Illuminate\Database\Query\Builder{return $this->db->table($this->table())->where('id',$companyId)->whereNull('deleted_at');}
}
