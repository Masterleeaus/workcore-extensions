<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Support\Http\Controllers\SupportTicketController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.support.route_prefix'))->middleware((array)config('workcore.support.middleware'))->name('api.workcore.v1.')->group(function(): void {
    Route::get('support-tickets',[SupportTicketController::class,'index'])->name('support-tickets.index');
    Route::post('support-queues',[SupportTicketController::class,'storeQueue'])->name('support-queues.store');
    Route::post('support-tickets',[SupportTicketController::class,'store'])->name('support-tickets.store');
    Route::post('support-tickets/{ticket}/messages',[SupportTicketController::class,'message'])->name('support-tickets.messages.store');
    Route::post('support-tickets/{ticket}/assign',[SupportTicketController::class,'assign'])->name('support-tickets.assign');
    Route::post('support-tickets/{ticket}/status',[SupportTicketController::class,'status'])->name('support-tickets.status');
    Route::post('support-tickets/{ticket}/work-order',[SupportTicketController::class,'workOrder'])->name('support-tickets.work-order');
});
