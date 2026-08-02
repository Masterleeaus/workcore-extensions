<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Controllers\KnowledgeBaseController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.knowledge.route_prefix'))->middleware((array)config('workcore.knowledge.middleware'))->name('api.workcore.v1.')->group(function(): void {
    Route::get('knowledge-articles',[KnowledgeBaseController::class,'index'])->name('knowledge-articles.index');
    Route::post('knowledge-categories',[KnowledgeBaseController::class,'storeCategory'])->name('knowledge-categories.store');
    Route::post('knowledge-articles',[KnowledgeBaseController::class,'store'])->name('knowledge-articles.store');
    Route::patch('knowledge-articles/{article}',[KnowledgeBaseController::class,'update'])->name('knowledge-articles.update');
    Route::post('knowledge-articles/{article}/status',[KnowledgeBaseController::class,'status'])->name('knowledge-articles.status');
    Route::post('knowledge-articles/{article}/feedback',[KnowledgeBaseController::class,'feedback'])->name('knowledge-articles.feedback');
    Route::post('support-tickets/{ticket}/knowledge-article',[KnowledgeBaseController::class,'fromTicket'])->name('support-tickets.knowledge-article');
});
