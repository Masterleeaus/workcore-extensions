<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MoveOpportunityRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['stage_public_id' => ['required', 'string', 'size:26'], 'position' => ['sometimes', 'integer', 'min:0']]; }
}
