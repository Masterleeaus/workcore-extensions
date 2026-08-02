<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class FormSubmissionResource
{
    public static function make(array|object $row): array { $r=(array)$row; foreach(['metadata'] as $k){if(isset($r[$k])&&is_string($r[$k]))$r[$k]=json_decode($r[$k],true);} return ['id'=>$r['public_id']??null,'type'=>'form_submission','attributes'=>array_diff_key($r,['public_id'=>true,'company_id'=>true,'id'=>true])]; }
}
