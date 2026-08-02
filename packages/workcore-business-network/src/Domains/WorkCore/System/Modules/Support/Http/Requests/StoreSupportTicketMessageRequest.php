<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreSupportTicketMessageRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'body'=>['required','string'],'message_type'=>['sometimes','in:reply,internal_note,system,request'],'visibility'=>['sometimes','in:public,internal'],
        'channel'=>['sometimes','string','max:50'],'author_worker_public_id'=>['nullable','string','size:26'],'author_contact_public_id'=>['nullable','string','size:26'],'attachments'=>['nullable','array'],
    ];}
}
