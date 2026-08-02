<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreKnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'category_public_id'=>['nullable','string','size:26'],'premises_public_id'=>['nullable','string','size:26'],'asset_public_id'=>['nullable','string','size:26'],'source_support_ticket_public_id'=>['nullable','string','size:26'],
        'slug'=>['nullable','string','max:180'],'title'=>['required','string','max:255'],'summary'=>['nullable','string'],'body'=>['required','string'],
        'article_type'=>['sometimes','in:procedure,troubleshooting,policy,resident_guide,property_manual,safety,faq,training,service_guide'],'visibility'=>['sometimes','in:internal,customer,resident,owner,contractor,public'],
        'audiences'=>['nullable','array'],'audiences.*'=>['string','max:50'],'keywords'=>['nullable','string'],'tags'=>['nullable','array'],'tags.*'=>['string','max:80'],'status'=>['sometimes','in:draft,review'],
        'pinned'=>['sometimes','boolean'],'requires_acknowledgement'=>['sometimes','boolean'],'review_due_on'=>['nullable','date'],'publish_at'=>['nullable','date'],'expires_at'=>['nullable','date','after:publish_at'],'metadata'=>['nullable','array'],
    ];}
}
