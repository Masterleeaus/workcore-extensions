<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class PremisesResource
{
    public static function make(array $row): array
    {
        return ['id'=>(string)($row['public_id']??''),'type'=>'premises','customer_id'=>$row['customer_public_id']??null,'customer_name'=>$row['customer_name']??null,'name'=>$row['name']??null,'address'=>['line_1'=>$row['address_line_1']??null,'line_2'=>$row['address_line_2']??null,'suburb'=>$row['suburb']??null,'state'=>$row['state']??null,'postcode'=>$row['postcode']??null,'country_code'=>$row['country_code']??null],'coordinates'=>['latitude'=>$row['latitude']??null,'longitude'=>$row['longitude']??null],'access_instructions'=>$row['access_instructions']??null,'parking_instructions'=>$row['parking_instructions']??null,'hazards'=>$row['hazards']??null,'service_requirements'=>$row['service_requirements']??null,'is_primary'=>(bool)($row['is_primary']??false),'status'=>$row['status']??'active'];
    }
}
