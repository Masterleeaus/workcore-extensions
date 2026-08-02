<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;
use App\Domains\WorkCore\System\Contracts\Records\KnowledgeArticleAccessContract;
final class KnowledgeArticleAccessAdapter extends AbstractDatabaseRecordAccess implements KnowledgeArticleAccessContract
{
    protected function table(): string{return 'tz_kb_articles';}
    protected function type(): string{return 'knowledge_article';}
    protected function columns(): array{return ['public_id','category_id','premises_id','asset_id','source_support_ticket_id','slug','title','summary','body','article_type','visibility','audiences','keywords','status','current_version','pinned','requires_acknowledgement','review_due_on','publish_at','expires_at','published_at','retired_at','metadata','created_at','updated_at'];}
}
