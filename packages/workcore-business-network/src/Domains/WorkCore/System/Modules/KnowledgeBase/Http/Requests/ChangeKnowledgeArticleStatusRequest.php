<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ChangeKnowledgeArticleStatusRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['status'=>['required','in:draft,review,published,retired,archived'],'reason'=>['nullable','string','max:1000']];}
}
