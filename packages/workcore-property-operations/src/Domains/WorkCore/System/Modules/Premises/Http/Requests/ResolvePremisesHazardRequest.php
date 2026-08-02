<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ResolvePremisesHazardRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'resolution_notes'=>['nullable','string','max:10000'],
        'control_measures'=>['nullable','string','max:10000'],
        'evidence_file_references'=>['nullable','array'],
        'evidence_file_references.*'=>['string','max:255'],
        'resolved_at'=>['nullable','date'],
    ]; }
}
