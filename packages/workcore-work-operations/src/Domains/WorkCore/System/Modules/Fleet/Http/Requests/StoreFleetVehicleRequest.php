<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreFleetVehicleRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return [
        'asset_public_id'=>['required','string','size:26'],'home_premises_public_id'=>['nullable','string','size:26'],'fleet_number'=>['required','string','max:100'],
        'registration_number'=>['required','string','max:100'],'vin'=>['nullable','string','max:100'],'make'=>['nullable','string','max:120'],'model'=>['nullable','string','max:120'],
        'year'=>['nullable','integer','between:1900,2200'],'vehicle_type'=>['sometimes','string','max:60'],'fuel_type'=>['nullable','string','max:40'],
        'odometer'=>['nullable','numeric','min:0'],'odometer_unit'=>['nullable','in:km,mi'],'registration_expires_on'=>['nullable','date'],'insurance_expires_on'=>['nullable','date'],
        'next_service_due_on'=>['nullable','date'],'next_service_due_odometer'=>['nullable','numeric','min:0'],'metadata'=>['nullable','array'],
    ];}
}
