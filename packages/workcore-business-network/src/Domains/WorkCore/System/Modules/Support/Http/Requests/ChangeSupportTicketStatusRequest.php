<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ChangeSupportTicketStatusRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['status'=>['required','in:open,triaged,assigned,in_progress,waiting_requester,waiting_external,resolved,closed,cancelled'],'reason'=>['nullable','string']];}
}
