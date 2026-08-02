<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ApproveWizardSectionRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['section_key'=>['required','string','max:150'],'summary'=>['nullable','array']]; } }
