<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class UpdateKnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'category_public_id'=>['nullable','string','size:26'],'title'=>['sometimes','string','max:255'],'summary'=>['nullable','string'],'body'=>['sometimes','string'],
        'article_type'=>['sometimes','in:procedure,troubleshooting,policy,resident_guide,property_manual,safety,faq,training,service_guide'],'visibility'=>['sometimes','in:internal,customer,resident,owner,contractor,public'],
        'audiences'=>['nullable','array'],'audiences.*'=>['string','max:50'],'keywords'=>['nullable','string'],'tags'=>['nullable','array'],'tags.*'=>['string','max:80'],'pinned'=>['sometimes','boolean'],
        'requires_acknowledgement'=>['sometimes','boolean'],'review_due_on'=>['nullable','date'],'publish_at'=>['nullable','date'],'expires_at'=>['nullable','date'],'metadata'=>['nullable','array'],'change_summary'=>['nullable','string','max:500'],
    ];}
}
