<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class TerritoryAssignmentResource
{ public static function make(array $row): array{return ['id'=>(string)($row['public_id']??''),'type'=>'territory_assignment','assignable_type'=>$row['assignable_type']??null,'assignable_id'=>$row['assignable_public_id']??null,'assignment_role'=>$row['assignment_role']??null,'priority'=>(int)($row['priority']??100),'effective_from'=>$row['effective_from']??null,'effective_until'=>$row['effective_until']??null,'active'=>(bool)($row['active']??true)];} }
