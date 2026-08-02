<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class LeaveRequestResource { public static function make(array|object $row): array { $r=(array)$row; return ['id'=>$r['public_id']??null,'type'=>'leave_request','attributes'=>array_diff_key($r,['public_id'=>true,'company_id'=>true,'id'=>true,'worker_id'=>true,'leave_type_id'=>true,'replacement_worker_id'=>true])]; } }
