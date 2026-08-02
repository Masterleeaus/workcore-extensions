<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class TerritoryNodeResource
{ public static function make(array $row): array{return ['id'=>(string)($row['public_id']??''),'type'=>$row['record_type']??'territory_node','code'=>$row['code']??null,'name'=>$row['name']??null,'description'=>$row['description']??null,'status'=>$row['status']??'active'];} }
