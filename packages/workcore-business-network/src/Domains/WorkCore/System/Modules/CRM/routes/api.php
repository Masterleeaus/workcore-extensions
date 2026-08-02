<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\CRM\Http\Controllers\ContactController;
use App\Domains\WorkCore\System\Modules\CRM\Http\Controllers\CrmActivityController;
use App\Domains\WorkCore\System\Modules\CRM\Http\Controllers\CustomerController;
use App\Domains\WorkCore\System\Modules\CRM\Http\Controllers\LeadController;
use App\Domains\WorkCore\System\Modules\CRM\Http\Controllers\OpportunityController;
use App\Domains\WorkCore\System\Modules\CRM\Http\Controllers\SalesPipelineController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.crm.route_prefix'))
    ->middleware((array) config('workcore.crm.middleware'))
    ->name('api.workcore.v1.')
    ->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::post('contacts/{contact}/primary', [ContactController::class, 'setPrimary'])->name('contacts.primary');
        Route::get('leads/options', [LeadController::class, 'options'])->name('leads.options');
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

        Route::get('sales-pipelines', [SalesPipelineController::class, 'index'])->name('sales-pipelines.index');
        Route::post('sales-pipelines', [SalesPipelineController::class, 'store'])->name('sales-pipelines.store');
        Route::get('sales-pipelines/{pipeline}/board', [SalesPipelineController::class, 'board'])->name('sales-pipelines.board');
        Route::get('crm/forecast', [SalesPipelineController::class, 'forecast'])->name('crm.forecast');

        Route::get('opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
        Route::post('opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');
        Route::patch('opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');
        Route::post('opportunities/{opportunity}/move', [OpportunityController::class, 'move'])->name('opportunities.move');
        Route::post('opportunities/{opportunity}/won', [OpportunityController::class, 'won'])->name('opportunities.won');
        Route::post('opportunities/{opportunity}/lost', [OpportunityController::class, 'lost'])->name('opportunities.lost');

        Route::get('crm/activities', [CrmActivityController::class, 'index'])->name('crm.activities.index');
        Route::post('crm/activities', [CrmActivityController::class, 'store'])->name('crm.activities.store');
    });
