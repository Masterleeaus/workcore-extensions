<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ConvertSupportTicketToWorkOrderRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['title'=>['nullable','string','max:255'],'description'=>['nullable','string'],'status'=>['sometimes','in:draft,ready'],'due_at'=>['nullable','date'],'services'=>['nullable','array']];}
}
