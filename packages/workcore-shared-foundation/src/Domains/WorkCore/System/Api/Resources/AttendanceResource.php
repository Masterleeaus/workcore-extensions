<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class AttendanceResource { public static function make(array|object $row): array { $r=(array)$row; return ['id'=>$r['public_id']??null,'type'=>'attendance_session','attributes'=>array_diff_key($r,['public_id'=>true,'company_id'=>true,'id'=>true,'worker_id'=>true,'premises_id'=>true])]; } }
