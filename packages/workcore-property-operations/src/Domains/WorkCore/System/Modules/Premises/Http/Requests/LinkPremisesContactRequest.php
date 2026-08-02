<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class LinkPremisesContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'contact_public_id'=>['required','string','size:26'],
        'role'=>['sometimes','string','max:60'],
        'primary'=>['sometimes','boolean'],
        'notification_preferences'=>['nullable','array'],
    ]; }
}
