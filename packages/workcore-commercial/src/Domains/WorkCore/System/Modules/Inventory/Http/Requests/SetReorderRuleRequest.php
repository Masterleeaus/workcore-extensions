<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class SetReorderRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['location_public_id'=>['required','string','size:26'],'reorder_point'=>['required','numeric','min:0'],'reorder_quantity'=>['required','numeric','min:0']]; }
}
