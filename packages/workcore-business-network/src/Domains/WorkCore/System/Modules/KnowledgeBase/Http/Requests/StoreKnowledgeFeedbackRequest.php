<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreKnowledgeFeedbackRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['rating'=>['required','in:helpful,not_helpful'],'comment'=>['nullable','string','max:2000'],'context'=>['nullable','array']];}
}
