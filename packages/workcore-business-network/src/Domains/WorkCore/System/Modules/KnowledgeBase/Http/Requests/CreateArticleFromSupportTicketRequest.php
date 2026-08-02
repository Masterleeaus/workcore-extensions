<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class CreateArticleFromSupportTicketRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'category_public_id'=>['nullable','string','size:26'],'premises_public_id'=>['nullable','string','size:26'],'title'=>['nullable','string','max:255'],'summary'=>['nullable','string'],'body'=>['nullable','string'],'resolution'=>['nullable','string'],
        'article_type'=>['sometimes','in:procedure,troubleshooting,policy,resident_guide,property_manual,safety,faq,training,service_guide'],'visibility'=>['sometimes','in:internal,customer,resident,owner,contractor,public'],'audiences'=>['nullable','array'],'keywords'=>['nullable','string'],'tags'=>['nullable','array'],'tags.*'=>['string','max:80'],'metadata'=>['nullable','array'],
    ];}
}
