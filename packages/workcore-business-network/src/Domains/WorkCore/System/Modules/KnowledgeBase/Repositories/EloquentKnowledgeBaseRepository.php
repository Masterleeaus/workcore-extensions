<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Contracts\KnowledgeBaseRepositoryContract;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Services\ArticleStatusPolicy;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Services\ArticleVisibilityPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentKnowledgeBaseRepository extends TenantScopedRepository implements KnowledgeBaseRepositoryContract
{
    public function __construct(ConnectionInterface $db,TenantContextContract $tenant,private ArticleStatusPolicy $statusPolicy,private ArticleVisibilityPolicy $visibilityPolicy){parent::__construct($db,$tenant);}

    public function search(int $companyId,array $filters=[],int $perPage=25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);$query=$this->baseQuery($companyId);
        if($q=trim((string)($filters['q']??''))){$query->where(function(Builder $b)use($q):void{$b->where('articles.title','like',"%{$q}%")->orWhere('articles.summary','like',"%{$q}%")->orWhere('articles.body','like',"%{$q}%")->orWhere('articles.keywords','like',"%{$q}%");});}
        foreach(['status'=>'articles.status','visibility'=>'articles.visibility','article_type'=>'articles.article_type'] as $f=>$c)if(!empty($filters[$f]))$query->where($c,$filters[$f]);
        if(!empty($filters['category_public_id']))$query->where('categories.public_id',$filters['category_public_id']);
        if(!empty($filters['premises_public_id']))$query->where('premises.public_id',$filters['premises_public_id']);
        if(!empty($filters['support_ticket_public_id']))$query->where('tickets.public_id',$filters['support_ticket_public_id']);
        if(!empty($filters['published_only'])){$query->where('articles.status','published')->where(fn(Builder $b)=>$b->whereNull('articles.publish_at')->orWhere('articles.publish_at','<=',now()))->where(fn(Builder $b)=>$b->whereNull('articles.expires_at')->orWhere('articles.expires_at','>',now()));}
        return $query->orderByDesc('articles.pinned')->orderByDesc('articles.published_at')->orderByDesc('articles.updated_at')->paginate(max(1,min($perPage,100)));
    }

    public function createCategory(array $data,int $companyId,int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$parent=$this->optionalRecord('tz_kb_categories',$companyId,$data['parent_public_id']??null,'Parent knowledge category');$visibility=(string)($data['visibility']??'internal');$this->visibilityPolicy->assertValid($visibility);
        $slug=trim((string)($data['slug']??''))?:Str::slug((string)$data['name']);if($slug==='')throw new InvalidArgumentException('Knowledge category slug could not be generated.');
        if($this->db->table('tz_kb_categories')->where('company_id',$companyId)->where('slug',$slug)->whereNull('deleted_at')->exists())throw new InvalidArgumentException('Knowledge category slug already exists for the active company.');
        $publicId=(string)Str::ulid();$this->db->table('tz_kb_categories')->insert(['public_id'=>$publicId,'company_id'=>$companyId,'parent_id'=>$parent?->id,'name'=>(string)$data['name'],'slug'=>$slug,'description'=>$data['description']??null,'visibility'=>$visibility,'sort_order'=>(int)($data['sort_order']??0),'active'=>(bool)($data['active']??true),'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
        return (array)$this->db->table('tz_kb_categories')->where('company_id',$companyId)->where('public_id',$publicId)->first();
    }

    public function createArticle(array $data,int $companyId,int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$category=$this->optionalRecord('tz_kb_categories',$companyId,$data['category_public_id']??null,'Knowledge category');$premises=$this->optionalRecord('tz_premises',$companyId,$data['premises_public_id']??null,'Premises');$asset=$this->optionalRecord('tz_assets',$companyId,$data['asset_public_id']??null,'Asset');$ticket=$this->optionalRecord('tz_support_tickets',$companyId,$data['source_support_ticket_public_id']??null,'Support ticket');
        $visibility=(string)($data['visibility']??'internal');$this->visibilityPolicy->assertValid($visibility);$slug=trim((string)($data['slug']??''))?:Str::slug((string)$data['title']);if($slug==='')throw new InvalidArgumentException('Knowledge article slug could not be generated.');
        if($this->db->table('tz_kb_articles')->where('company_id',$companyId)->where('slug',$slug)->whereNull('deleted_at')->exists())throw new InvalidArgumentException('Knowledge article slug already exists for the active company.');
        $status=(string)($data['status']??'draft');if(!in_array($status,['draft','review'],true))throw new InvalidArgumentException('New knowledge articles must start in draft or review.');$publicId=(string)Str::ulid();
        return $this->db->transaction(function()use($data,$companyId,$actorId,$category,$premises,$asset,$ticket,$visibility,$slug,$status,$publicId):array{
            $articleId=$this->db->table('tz_kb_articles')->insertGetId(['public_id'=>$publicId,'company_id'=>$companyId,'category_id'=>$category?->id,'premises_id'=>$premises?->id,'asset_id'=>$asset?->id,'source_support_ticket_id'=>$ticket?->id,'slug'=>$slug,'title'=>(string)$data['title'],'summary'=>$data['summary']??null,'body'=>(string)$data['body'],'article_type'=>(string)($data['article_type']??'procedure'),'visibility'=>$visibility,'audiences'=>isset($data['audiences'])?json_encode(array_values($data['audiences']),JSON_THROW_ON_ERROR):null,'keywords'=>$data['keywords']??null,'status'=>$status,'current_version'=>1,'pinned'=>(bool)($data['pinned']??false),'requires_acknowledgement'=>(bool)($data['requires_acknowledgement']??false),'review_due_on'=>$data['review_due_on']??null,'publish_at'=>$data['publish_at']??null,'expires_at'=>$data['expires_at']??null,'metadata'=>isset($data['metadata'])?json_encode($data['metadata'],JSON_THROW_ON_ERROR):null,'created_by_user_id'=>$actorId,'updated_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);
            $this->appendVersion($companyId,$articleId,1,$data,$actorId,'Initial draft');$this->syncTags($companyId,$articleId,(array)($data['tags']??[]),$actorId);return $this->findByPublicId($companyId,$publicId)??['public_id'=>$publicId];
        },3);
    }

    public function updateArticle(string $articlePublicId,array $data,int $companyId,int $actorId): array
    {
        $this->assertActor($companyId,$actorId);return $this->db->transaction(function()use($articlePublicId,$data,$companyId,$actorId):array{
            $article=$this->articleForUpdate($companyId,$articlePublicId);if((string)$article->status==='archived')throw new InvalidArgumentException('Archived knowledge articles must be reactivated before editing.');
            $category=array_key_exists('category_public_id',$data)?$this->optionalRecord('tz_kb_categories',$companyId,$data['category_public_id'],'Knowledge category'):null;$visibility=(string)($data['visibility']??$article->visibility);$this->visibilityPolicy->assertValid($visibility);$next=(int)$article->current_version+1;
            $updates=['title'=>(string)($data['title']??$article->title),'summary'=>array_key_exists('summary',$data)?$data['summary']:$article->summary,'body'=>(string)($data['body']??$article->body),'article_type'=>(string)($data['article_type']??$article->article_type),'visibility'=>$visibility,'audiences'=>array_key_exists('audiences',$data)?json_encode(array_values((array)$data['audiences']),JSON_THROW_ON_ERROR):$article->audiences,'keywords'=>array_key_exists('keywords',$data)?$data['keywords']:$article->keywords,'pinned'=>array_key_exists('pinned',$data)?(bool)$data['pinned']:(bool)$article->pinned,'requires_acknowledgement'=>array_key_exists('requires_acknowledgement',$data)?(bool)$data['requires_acknowledgement']:(bool)$article->requires_acknowledgement,'review_due_on'=>array_key_exists('review_due_on',$data)?$data['review_due_on']:$article->review_due_on,'publish_at'=>array_key_exists('publish_at',$data)?$data['publish_at']:$article->publish_at,'expires_at'=>array_key_exists('expires_at',$data)?$data['expires_at']:$article->expires_at,'metadata'=>array_key_exists('metadata',$data)?json_encode($data['metadata'],JSON_THROW_ON_ERROR):$article->metadata,'current_version'=>$next,'updated_by_user_id'=>$actorId,'updated_at'=>now()];if(array_key_exists('category_public_id',$data))$updates['category_id']=$category?->id;
            $this->db->table('tz_kb_articles')->where('id',$article->id)->update($updates);$this->appendVersion($companyId,(int)$article->id,$next,array_merge((array)$article,$updates),$actorId,(string)($data['change_summary']??'Article updated'));if(array_key_exists('tags',$data))$this->syncTags($companyId,(int)$article->id,(array)$data['tags'],$actorId);return $this->findByPublicId($companyId,$articlePublicId)??['public_id'=>$articlePublicId];
        },3);
    }

    public function changeStatus(string $articlePublicId,array $data,int $companyId,int $actorId): array
    {
        $this->assertActor($companyId,$actorId);return $this->db->transaction(function()use($articlePublicId,$data,$companyId,$actorId):array{$article=$this->articleForUpdate($companyId,$articlePublicId);$to=(string)$data['status'];$reason=isset($data['reason'])?trim((string)$data['reason']):null;$this->statusPolicy->assertTransition((string)$article->status,$to,$reason);$updates=['status'=>$to,'updated_by_user_id'=>$actorId,'updated_at'=>now()];if($to==='published'){$updates['published_at']=now();$updates['published_by_user_id']=$actorId;}if($to==='retired')$updates['retired_at']=now();if(in_array($to,['draft','review'],true))$updates['retired_at']=null;$this->db->table('tz_kb_articles')->where('id',$article->id)->update($updates);$this->db->table('tz_kb_article_status_history')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'knowledge_article_id'=>$article->id,'from_status'=>$article->status,'to_status'=>$to,'reason'=>$reason,'actor_user_id'=>$actorId,'changed_at'=>now(),'created_at'=>now()]);return $this->findByPublicId($companyId,$articlePublicId)??['public_id'=>$articlePublicId];},3);
    }

    public function createFromSupportTicket(string $ticketPublicId,array $data,int $companyId,int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$ticket=$this->optionalRecord('tz_support_tickets',$companyId,$ticketPublicId,'Support ticket');if(!$ticket)throw new InvalidArgumentException('Support ticket does not belong to the active company.');if(!in_array((string)$ticket->status,['resolved','closed'],true))throw new InvalidArgumentException('Only resolved or closed support tickets can be converted into knowledge articles.');
        return $this->createArticle(['category_public_id'=>$data['category_public_id']??null,'premises_public_id'=>$data['premises_public_id']??null,'source_support_ticket_public_id'=>$ticketPublicId,'title'=>$data['title']??$ticket->subject,'summary'=>$data['summary']??'Knowledge captured from support ticket '.$ticket->ticket_number.'.','body'=>$data['body']??"Issue\n\n{$ticket->description}\n\nResolution\n\n".($data['resolution']??'Document the verified resolution before publishing.'),'article_type'=>$data['article_type']??'troubleshooting','visibility'=>$data['visibility']??'internal','audiences'=>$data['audiences']??['internal'],'keywords'=>$data['keywords']??$ticket->category,'tags'=>$data['tags']??['support-derived'],'metadata'=>array_merge((array)($data['metadata']??[]),['source_ticket_number'=>$ticket->ticket_number,'source_ticket_public_id'=>$ticketPublicId])],$companyId,$actorId);
    }

    public function recordFeedback(string $articlePublicId,array $data,int $companyId,int $actorId): array
    {
        $this->assertActor($companyId,$actorId);$article=$this->optionalRecord('tz_kb_articles',$companyId,$articlePublicId,'Knowledge article');if(!$article)throw new InvalidArgumentException('Knowledge article does not belong to the active company.');$rating=(string)$data['rating'];if(!in_array($rating,['helpful','not_helpful'],true))throw new InvalidArgumentException('Knowledge feedback rating must be helpful or not_helpful.');$publicId=(string)Str::ulid();$this->db->table('tz_kb_article_feedback')->insert(['public_id'=>$publicId,'company_id'=>$companyId,'knowledge_article_id'=>$article->id,'user_id'=>$actorId,'rating'=>$rating,'comment'=>$data['comment']??null,'context'=>isset($data['context'])?json_encode($data['context'],JSON_THROW_ON_ERROR):null,'created_at'=>now()]);return (array)$this->db->table('tz_kb_article_feedback')->where('company_id',$companyId)->where('public_id',$publicId)->first();
    }

    public function findByPublicId(int $companyId,string $publicId): ?array
    {
        $this->assertCompany($companyId);$row=$this->baseQuery($companyId)->where('articles.public_id',$publicId)->first();if(!$row)return null;$record=(array)$row;$articleId=(int)$record['internal_id'];unset($record['internal_id']);$record['tags']=$this->db->table('tz_kb_tags as tags')->join('tz_kb_article_tags as links','links.knowledge_tag_id','=','tags.id')->where('links.company_id',$companyId)->where('links.knowledge_article_id',$articleId)->orderBy('tags.name')->pluck('tags.name')->all();$record['versions']=$this->db->table('tz_kb_article_versions')->where('company_id',$companyId)->where('knowledge_article_id',$articleId)->orderByDesc('version_number')->get(['public_id','version_number','title','summary','change_summary','created_by_user_id','created_at'])->map(static fn(object $i):array=>(array)$i)->all();return $record;
    }

    private function baseQuery(int $companyId): Builder
    {
        return $this->db->table('tz_kb_articles as articles')->leftJoin('tz_kb_categories as categories','categories.id','=','articles.category_id')->leftJoin('tz_premises as premises','premises.id','=','articles.premises_id')->leftJoin('tz_assets as assets','assets.id','=','articles.asset_id')->leftJoin('tz_support_tickets as tickets','tickets.id','=','articles.source_support_ticket_id')->where('articles.company_id',$companyId)->whereNull('articles.deleted_at')->select(['articles.id as internal_id','articles.public_id','articles.slug','articles.title','articles.summary','articles.body','articles.article_type','articles.visibility','articles.audiences','articles.keywords','articles.status','articles.current_version','articles.pinned','articles.requires_acknowledgement','articles.review_due_on','articles.publish_at','articles.expires_at','articles.published_at','articles.retired_at','articles.metadata','articles.created_at','articles.updated_at','categories.public_id as category_public_id','categories.name as category_name','premises.public_id as premises_public_id','premises.name as premises_name','assets.public_id as asset_public_id','assets.name as asset_name','tickets.public_id as source_support_ticket_public_id','tickets.ticket_number as source_ticket_number']);
    }

    private function optionalRecord(string $table,int $companyId,mixed $publicId,string $label): ?object
    {
        if($publicId===null||trim((string)$publicId)==='')return null;$q=$this->db->table($table)->where('company_id',$companyId)->where('public_id',(string)$publicId);if(in_array($table,['tz_kb_categories','tz_kb_articles'],true))$q->whereNull('deleted_at');$r=$q->first();if(!$r)throw new InvalidArgumentException("{$label} does not belong to the active company.");return $r;
    }
    private function articleForUpdate(int $companyId,string $publicId): object{$a=$this->db->table('tz_kb_articles')->where('company_id',$companyId)->where('public_id',$publicId)->whereNull('deleted_at')->lockForUpdate()->first();if(!$a)throw new InvalidArgumentException('Knowledge article does not belong to the active company.');return $a;}
    private function appendVersion(int $companyId,int $articleId,int $version,array $data,int $actorId,string $summary): void{$this->db->table('tz_kb_article_versions')->insert(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'knowledge_article_id'=>$articleId,'version_number'=>$version,'title'=>(string)($data['title']??''),'summary'=>$data['summary']??null,'body'=>(string)($data['body']??''),'article_type'=>(string)($data['article_type']??'procedure'),'visibility'=>(string)($data['visibility']??'internal'),'audiences'=>isset($data['audiences'])&&is_array($data['audiences'])?json_encode(array_values($data['audiences']),JSON_THROW_ON_ERROR):($data['audiences']??null),'keywords'=>$data['keywords']??null,'change_summary'=>$summary,'created_by_user_id'=>$actorId,'created_at'=>now()]);}
    private function syncTags(int $companyId,int $articleId,array $tags,int $actorId): void{$this->db->table('tz_kb_article_tags')->where('company_id',$companyId)->where('knowledge_article_id',$articleId)->delete();foreach(array_values(array_unique(array_filter(array_map(static fn(mixed $t):string=>trim((string)$t),$tags)))) as $name){$slug=Str::slug($name);if($slug==='')continue;$tag=$this->db->table('tz_kb_tags')->where('company_id',$companyId)->where('slug',$slug)->first();$tagId=$tag?->id??$this->db->table('tz_kb_tags')->insertGetId(['public_id'=>(string)Str::ulid(),'company_id'=>$companyId,'name'=>$name,'slug'=>$slug,'created_by_user_id'=>$actorId,'created_at'=>now(),'updated_at'=>now()]);$this->db->table('tz_kb_article_tags')->insert(['company_id'=>$companyId,'knowledge_article_id'=>$articleId,'knowledge_tag_id'=>$tagId,'created_at'=>now()]);}}
    private function assertActor(int $companyId,int $actorId): void{$this->assertCompany($companyId);if($actorId<1||$actorId!==$this->actorId())throw new InvalidArgumentException('Knowledge action actor does not match the active WorkCore user.');}
}
