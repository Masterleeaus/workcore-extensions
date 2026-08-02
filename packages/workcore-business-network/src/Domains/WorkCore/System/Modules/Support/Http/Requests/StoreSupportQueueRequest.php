<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreSupportQueueRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'code'=>['required','string','max:60','regex:/^[a-zA-Z0-9_-]+$/'],'name'=>['required','string','max:160'],'description'=>['nullable','string'],
        'default_priority'=>['sometimes','in:low,normal,high,urgent,emergency'],'first_response_minutes'=>['nullable','integer','min:1','max:525600'],
        'resolution_minutes'=>['nullable','integer','min:1','max:525600'],'channel_defaults'=>['nullable','array'],'active'=>['sometimes','boolean'],
    ];}
}
