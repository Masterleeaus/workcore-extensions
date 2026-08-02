<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreSkillRequest extends FormRequest
{public function authorize():bool{return true;} public function rules():array{return ['name'=>['required','string','max:150'],'code'=>['nullable','string','max:100'],'description'=>['nullable','string','max:5000'],'category'=>['nullable','string','max:100'],'requires_certification'=>['sometimes','boolean'],'active'=>['sometimes','boolean']];}}
