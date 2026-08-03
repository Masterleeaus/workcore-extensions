<?php

declare(strict_types=1);

use App\Extensions\WorkCore\System\Http\Controllers\WorkspaceController;
use App\Extensions\WorkCore\System\Http\Middleware\RequireWorkspaceCapability;
use App\Extensions\WorkCore\System\Navigation\WorkCoreWorkspaceCatalogue;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/** @var Router $router */
$router = app('router');
$router->aliasMiddleware('workcore.workspace-capability', RequireWorkspaceCapability::class);

/** @var WorkCoreWorkspaceCatalogue $catalogue */
$catalogue = app(WorkCoreWorkspaceCatalogue::class);

Route::prefix('dashboard/user/workcore')
    ->middleware(['web', 'auth', 'workcore.tenant'])
    ->group(function () use ($catalogue): void {
        foreach ($catalogue->all() as $workspaceKey => $workspace) {
            $rootCapabilities = $workspace['capabilities'];
            foreach ($workspace['sections'] as $section) {
                foreach ($section['capabilities'] as $capability) {
                    $rootCapabilities[] = $capability;
                }
            }
            $rootCapabilities = array_values(array_unique($rootCapabilities));

            Route::get($workspace['path'], [WorkspaceController::class, 'show'])
                ->defaults('workspace', $workspaceKey)
                ->defaults('section', null)
                ->middleware('workcore.workspace-capability:' . implode('|', $rootCapabilities))
                ->name($workspace['route_name']);

            foreach ($workspace['sections'] as $sectionKey => $section) {
                Route::get($section['path'], [WorkspaceController::class, 'show'])
                    ->defaults('workspace', $workspaceKey)
                    ->defaults('section', $sectionKey)
                    ->middleware('workcore.workspace-capability:' . implode('|', $section['capabilities']))
                    ->name($section['route_name']);
            }
        }
    });
