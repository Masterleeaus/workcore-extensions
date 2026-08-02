<?php
declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['required','string','max:255'], 'email' => ['nullable','email','max:255','required_without:phone'], 'company_name' => ['nullable','string','max:255'], 'phone' => ['nullable','string','max:50','required_without:email'], 'note' => ['nullable','string','max:5000'], 'source_public_id' => ['nullable','string','size:26'], 'status_public_id' => ['nullable','string','size:26'], 'owner_id' => ['nullable','integer']]; }
}
