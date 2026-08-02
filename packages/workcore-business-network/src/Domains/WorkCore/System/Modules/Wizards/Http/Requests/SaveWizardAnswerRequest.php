<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class SaveWizardAnswerRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['question_key'=>['required','string','max:255'],'value'=>['present'],'source'=>['sometimes','in:user_entered,ai_extracted,ai_suggested,existing_company_data,imported,system_default'],'confidence'=>['nullable','numeric','between:0,1'],'is_confirmed'=>['nullable','boolean'],'agent_public_id'=>['nullable','string','max:64']]; } }
