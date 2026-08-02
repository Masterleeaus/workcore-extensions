<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class AssignWorkerSkillRequest extends FormRequest
{public function authorize():bool{return true;} public function rules():array{return ['skill_public_id'=>['required','string','size:26'],'proficiency'=>['sometimes','in:trainee,basic,competent,advanced,expert'],'years_experience'=>['nullable','integer','min:0','max:80'],'verified'=>['sometimes','boolean'],'notes'=>['nullable','string','max:5000']];}}
