<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class TerritoryResource
{
 public static function make(array $row): array
 {
  return ['id'=>(string)($row['public_id']??''),'type'=>'territory','code'=>$row['code']??null,'name'=>$row['name']??null,'description'=>$row['description']??null,
   'territory_type'=>$row['territory_type']??null,'coverage'=>['postcode_patterns'=>self::json($row['postcode_patterns']??[]),'suburbs'=>self::json($row['suburbs']??[]),'state'=>$row['state']??null,'country_code'=>$row['country_code']??null,'centre'=>['latitude'=>$row['centre_latitude']??null,'longitude'=>$row['centre_longitude']??null],'radius_km'=>$row['radius_km']??null,'polygon_geojson'=>$row['polygon_geojson']??null],
   'hierarchy'=>['region_id'=>$row['region_public_id']??null,'region_name'=>$row['region_name']??null,'district_id'=>$row['district_public_id']??null,'district_name'=>$row['district_name']??null,'branch_id'=>$row['branch_public_id']??null,'branch_name'=>$row['branch_name']??null],
   'status'=>$row['status']??'active','priority'=>(int)($row['priority']??100),'match_score'=>isset($row['match_score'])?(int)$row['match_score']:null];
 }
 private static function json(mixed $v): array {if(is_array($v))return $v;if(is_string($v)&&$v!==''){ $d=json_decode($v,true);return is_array($d)?$d:[];}return [];}
}
