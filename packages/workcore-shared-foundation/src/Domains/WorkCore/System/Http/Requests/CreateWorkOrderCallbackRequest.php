<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class CreateWorkOrderCallbackRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'source_work_order_public_id'=>['required','string','size:26'],
        'callback_work_order_public_id'=>['required','string','size:26','different:source_work_order_public_id'],
        'warranty_public_id'=>['nullable','string','size:26'], 'reason_code'=>['required','string','max:80'],
        'reason'=>['nullable','string','max:10000'], 'chargeable'=>['nullable','boolean'],
    ]; }
}
