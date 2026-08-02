<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentNumberGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceFormatter;
use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceKey;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseDocumentNumberGenerator implements DocumentNumberGenerator
{
    public function __construct(private readonly IdentifierGenerator $ids, private readonly SequenceFormatter $formatter) {}

    public function next(string $companyId, SequenceKey $sequence, DateTimeImmutable $effectiveAt): string
    {
        return DB::transaction(function () use ($companyId, $sequence): string {
            $row = DB::table('tm_number_sequences')->where('company_id',$companyId)->where('sequence_key',$sequence->value)->where('period_key','global')->lockForUpdate()->first();
            if ($row === null) {
                $configured = config('titan-money.sequence_defaults.' . $sequence->value, []);
                $prefix = is_string($configured['prefix'] ?? null) ? $configured['prefix'] : strtoupper(substr($sequence->value, 0, 3)) . '-';
                $padding = is_int($configured['padding'] ?? null) ? $configured['padding'] : 6;
                DB::table('tm_number_sequences')->insert([
                    'id'=>$this->ids->uuid(),'company_id'=>$companyId,'sequence_key'=>$sequence->value,'period_key'=>'global','prefix'=>$prefix,'suffix'=>'','next_value'=>2,'padding'=>$padding,'reset_policy'=>'never','lock_version'=>1,
                    'created_by_actor_type'=>'system','created_by_actor_id'=>'titan-money-numbering','updated_by_actor_type'=>'system','updated_by_actor_id'=>'titan-money-numbering','created_at'=>now(),'updated_at'=>now(),
                ]);
                return $this->formatter->format($prefix,1,$padding);
            }
            $value=(int)$row->next_value;
            DB::table('tm_number_sequences')->where('id',$row->id)->update(['next_value'=>$value+1,'lock_version'=>DB::raw('lock_version + 1'),'updated_by_actor_type'=>'system','updated_by_actor_id'=>'titan-money-numbering','updated_at'=>now()]);
            return $this->formatter->format((string)$row->prefix,$value,(int)$row->padding,(string)$row->suffix);
        },3);
    }
}
