<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['customer_public_id'=>['required','string','size:26'],'name'=>['required','string','max:255'],'email'=>['nullable','email','max:255','required_without:phone'],'phone'=>['nullable','string','max:50','required_without:email'],'role'=>['nullable','string','max:100'],'is_primary'=>['sometimes','boolean']]; }
}
