<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\KnowledgeArticleAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\KnowledgeArticleAccessAdapter;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Actions\{ChangeKnowledgeArticleStatus,CreateKnowledgeArticle,CreateKnowledgeArticleFromSupportTicket,CreateKnowledgeCategory,RecordKnowledgeFeedback,SearchKnowledgeArticles,UpdateKnowledgeArticle};
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Contracts\KnowledgeBaseRepositoryContract;
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Repositories\EloquentKnowledgeBaseRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkKnowledgeBaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(KnowledgeBaseRepositoryContract::class,EloquentKnowledgeBaseRepository::class);$this->app->bind(KnowledgeArticleAccessContract::class,KnowledgeArticleAccessAdapter::class);$a=$this->app->make(BusinessActionRegistry::class);
        $a->register(new ActionDefinition('workcore.knowledge.category.create',CreateKnowledgeCategory::class,'medium',true,'workcore.knowledge',(string)config('workcore.knowledge.permissions.manage_categories')));
        $a->register(new ActionDefinition('workcore.knowledge.article.create',CreateKnowledgeArticle::class,'medium',true,'workcore.knowledge',(string)config('workcore.knowledge.permissions.create')));
        $a->register(new ActionDefinition('workcore.knowledge.article.update',UpdateKnowledgeArticle::class,'medium',true,'workcore.knowledge',(string)config('workcore.knowledge.permissions.edit')));
        $a->register(new ActionDefinition('workcore.knowledge.article.change_status',ChangeKnowledgeArticleStatus::class,'high',true,'workcore.knowledge',(string)config('workcore.knowledge.permissions.publish')));
        $a->register(new ActionDefinition('workcore.knowledge.article.create_from_support_ticket',CreateKnowledgeArticleFromSupportTicket::class,'medium',true,'workcore.knowledge',(string)config('workcore.knowledge.permissions.create')));
        $a->register(new ActionDefinition('workcore.knowledge.feedback.record',RecordKnowledgeFeedback::class,'low',true,'workcore.knowledge',(string)config('workcore.knowledge.permissions.feedback')));
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.knowledge.article.search',SearchKnowledgeArticles::class,'workcore.knowledge', permission: (string) config('workcore.knowledge.permissions.view')));
    }
    public function boot(): void{if((bool)config('workcore.knowledge.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}
}
