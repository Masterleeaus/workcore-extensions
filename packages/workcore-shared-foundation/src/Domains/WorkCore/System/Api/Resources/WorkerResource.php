<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class WorkerResource
{
 public static function make(array|object $row): array
 { $r=(array)$row; foreach(['service_areas','metadata'] as $f){if(isset($r[$f])&&is_string($r[$f]))$r[$f]=json_decode($r[$f],true);} return ['id'=>$r['public_id']??null,'type'=>'worker','attributes'=>array_diff_key($r,['public_id'=>true,'company_id'=>true])]; }
}
