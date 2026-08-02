<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\AI\Http\Controllers\{NativeAiApprovalController,NativeAiConversationController,NativeAiMemoryController};
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore_ai.orchestration.route_prefix', 'api/v1/workcore/ai'))
    ->middleware((array) config('workcore_ai.orchestration.middleware', ['api', 'auth', 'workcore.tenant', 'workcore.api']))
    ->name('api.workcore.ai.')
    ->group(function (): void {
        Route::get('conversations', [NativeAiConversationController::class, 'index'])->name('conversations.index');
        Route::get('conversations/{conversation}', [NativeAiConversationController::class, 'show'])->name('conversations.show');
        Route::post('turns', [NativeAiConversationController::class, 'turn'])->name('turns.store');
        Route::post('approvals/{approval}', [NativeAiApprovalController::class, 'decide'])->name('approvals.decide');
        Route::post('approvals/{approval}/resume', [NativeAiConversationController::class, 'resume'])->name('approvals.resume');
        Route::get('memories', [NativeAiMemoryController::class, 'index'])->name('memories.index');
        Route::post('memories', [NativeAiMemoryController::class, 'store'])->name('memories.store');
        Route::delete('memories/{memory}', [NativeAiMemoryController::class, 'destroy'])->name('memories.destroy');
    });
