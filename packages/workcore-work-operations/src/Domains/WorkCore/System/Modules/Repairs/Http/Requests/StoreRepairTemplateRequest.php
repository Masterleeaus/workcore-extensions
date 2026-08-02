<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreRepairTemplateRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['code'=>['required','string','max:100'],'name'=>['required','string','max:200'],'repair_type'=>['sometimes','string','max:60'],'default_tasks'=>['nullable','array'],'required_parts'=>['nullable','array'],'active'=>['sometimes','boolean']];}
}
