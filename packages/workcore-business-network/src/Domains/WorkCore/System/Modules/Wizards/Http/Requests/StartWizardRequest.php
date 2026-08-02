<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StartWizardRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['definition_key'=>['required','string','max:150'],'mode'=>['sometimes','in:guided,conversation,hybrid'],'agent_public_id'=>['nullable','string','max:64'],'metadata'=>['nullable','array']]; } }
