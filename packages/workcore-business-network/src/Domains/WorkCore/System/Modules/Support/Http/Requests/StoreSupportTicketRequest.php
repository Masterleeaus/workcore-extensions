<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'queue_public_id'=>['nullable','string','size:26'],'customer_public_id'=>['nullable','string','size:26'],'contact_public_id'=>['nullable','string','size:26'],
        'premises_public_id'=>['nullable','string','size:26'],'work_order_public_id'=>['nullable','string','size:26'],'appointment_public_id'=>['nullable','string','size:26'],
        'asset_public_id'=>['nullable','string','size:26'],'assigned_worker_public_id'=>['nullable','string','size:26'],'ticket_number'=>['nullable','string','max:64'],
        'subject'=>['required','string','max:255'],'description'=>['required','string'],'category'=>['sometimes','string','max:80'],
        'source'=>['sometimes','in:manual,portal,email,sms,phone,chatbot,whatsapp,telegram,messenger,internal,inspection,form,asset_alert'],
        'requester_type'=>['sometimes','in:resident,tenant,owner,agent,customer,contractor,internal,applicant,external'],
        'requester_name'=>['nullable','string','max:160'],'requester_email'=>['nullable','email','max:255'],'requester_phone'=>['nullable','string','max:50'],
        'priority'=>['sometimes','in:low,normal,high,urgent,emergency'],'status'=>['sometimes','in:open,triaged,assigned'],
        'first_response_minutes'=>['nullable','integer','min:1'],'resolution_minutes'=>['nullable','integer','min:1'],'confidential'=>['sometimes','boolean'],'metadata'=>['nullable','array'],
    ];}
}
