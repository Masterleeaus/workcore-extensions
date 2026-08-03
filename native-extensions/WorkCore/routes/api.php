<?php

declare(strict_types=1);

use App\Extensions\WorkCore\System\Http\Controllers\WorkspaceManifestController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.api.route_prefix', 'api/v1/workcore'))
    ->middleware((array) config('workcore-native.api_middleware', [
        'api',
        'auth:api',
        'workcore.tenant',
        'workcore.api',
    ]))
    ->group(function (): void {
        Route::get('workspaces', [WorkspaceManifestController::class, 'index'])
            ->name('api.workcore.workspaces.index');
        Route::get('workspaces/{workspace}', [WorkspaceManifestController::class, 'show'])
            ->where('workspace', '[a-z][a-z0-9-]*')
            ->name('api.workcore.workspaces.show');
    });
