<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalendarFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (function_exists('user_can')) return user_can('managedpremises.calendar.view') || user_can('managedpremises.view');
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable','date'],
            'to' => ['nullable','date','after_or_equal:from'],
        ];
    }
}
