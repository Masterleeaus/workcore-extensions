<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MarkOpportunityLostRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['reason' => ['nullable', 'string', 'max:2000']]; }
}
