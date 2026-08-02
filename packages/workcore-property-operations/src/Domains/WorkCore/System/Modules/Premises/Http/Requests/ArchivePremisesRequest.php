<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ArchivePremisesRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['reason'=>['required','string','max:5000'],'archived_at'=>['nullable','date']]; }
}
