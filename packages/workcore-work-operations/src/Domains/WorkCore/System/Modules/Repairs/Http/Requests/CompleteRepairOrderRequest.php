<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class CompleteRepairOrderRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['repair_actions'=>['required','string','max:20000'],'parts_references'=>['nullable','array'],'actual_cost'=>['nullable','numeric','min:0'],'completed_at'=>['nullable','date'],'return_to_service_approved'=>['required','accepted'],'evidence_file_references'=>['nullable','array'],'evidence_file_references.*'=>['string','max:255']];}
}
