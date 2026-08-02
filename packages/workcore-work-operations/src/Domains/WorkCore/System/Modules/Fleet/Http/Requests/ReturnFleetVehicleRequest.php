<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ReturnFleetVehicleRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['returned_at'=>['nullable','date'],'notes'=>['nullable','string','max:5000']];}
}
