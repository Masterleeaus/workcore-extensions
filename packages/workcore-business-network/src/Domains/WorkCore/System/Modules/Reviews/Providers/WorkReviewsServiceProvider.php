<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Reviews\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\ReviewAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\ReviewAccessAdapter;
use App\Domains\WorkCore\System\Modules\Reviews\Actions\{CreateReviewRequest,RecordReview,PublishReview,CreateServiceRecovery,RequestRebooking,SearchReviews};
use App\Domains\WorkCore\System\Modules\Reviews\Contracts\ReviewRepositoryContract;
use App\Domains\WorkCore\System\Modules\Reviews\Repositories\EloquentReviewRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkReviewsServiceProvider extends ServiceProvider
{
 public function register(): void { $this->app->bind(ReviewRepositoryContract::class,EloquentReviewRepository::class);$this->app->bind(ReviewAccessContract::class,ReviewAccessAdapter::class);$a=$this->app->make(BusinessActionRegistry::class);$p=(array)config('workcore.reviews.permissions');
  $a->register(new ActionDefinition('workcore.review.request.create',CreateReviewRequest::class,'low',true,'workcore.reviews',(string)$p['request']));
  $a->register(new ActionDefinition('workcore.review.record',RecordReview::class,'low',true,'workcore.reviews',(string)$p['record']));
  $a->register(new ActionDefinition('workcore.review.publish',PublishReview::class,'high',true,'workcore.reviews',(string)$p['publish']));
  $a->register(new ActionDefinition('workcore.service_recovery.create',CreateServiceRecovery::class,'high',true,'workcore.reviews',(string)$p['recovery']));
  $a->register(new ActionDefinition('workcore.rebooking.request',RequestRebooking::class,'medium',true,'workcore.reviews',(string)$p['rebook']));
  $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.review.search',SearchReviews::class,'workcore.reviews', permission: (string) config('workcore.reviews.permissions.view'))); }
 public function boot(): void { if((bool)config('workcore.reviews.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php'); }
}
