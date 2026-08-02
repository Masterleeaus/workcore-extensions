<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class AssignSupportTicketRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['worker_public_id'=>['required','string','size:26'],'reason'=>['nullable','string']];}
}
