<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class ComplianceObligationResource
{
    public static function make(array|object $row): array
    {
        $record=(array)$row;
        if(isset($record['metadata'])&&is_string($record['metadata']))$record['metadata']=json_decode($record['metadata'],true);
        return ['id'=>$record['public_id']??null,'type'=>'compliance_obligation','attributes'=>array_diff_key($record,['public_id'=>true,'company_id'=>true,'id'=>true])];
    }
}
