<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class CreateWizardDefinitionRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['key'=>['required','string','max:150'],'version'=>['sometimes','integer','min:1'],'title'=>['required','string','max:255'],'description'=>['nullable','string','max:2000'],'category'=>['nullable','string','max:100'],'modes'=>['nullable','array'],'sections'=>['required','array','min:1'],'sections.*.key'=>['required','string','max:150'],'sections.*.title'=>['required','string','max:255'],'sections.*.questions'=>['required','array']]; } }
