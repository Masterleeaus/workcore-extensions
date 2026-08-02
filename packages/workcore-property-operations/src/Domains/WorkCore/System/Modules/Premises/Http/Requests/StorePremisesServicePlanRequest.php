<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesServicePlanRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'space_public_id'=>['nullable','string','size:26'],'service_public_id'=>['nullable','string','size:26'],
        'name'=>['required','string','max:200'],'frequency_type'=>['sometimes','string','in:recurring,on_demand,seasonal,usage_based'],
        'interval_value'=>['nullable','integer','min:1'],'interval_unit'=>['nullable','string','in:day,week,month,quarter,year'],
        'starts_on'=>['nullable','date'],'ends_on'=>['nullable','date','after_or_equal:starts_on'],'next_due_on'=>['nullable','date'],
        'status'=>['sometimes','string','in:draft,active,paused,completed,archived'],'requirements'=>['nullable','array'],
        'task_template_public_ids'=>['nullable','array'],'task_template_public_ids.*'=>['string','size:26'],'notes'=>['nullable','string','max:5000'],
    ];}
}
