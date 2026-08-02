<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Compliance\Http\Controllers\ComplianceController;
use Illuminate\Support\Facades\Route;
Route::prefix((string) config('workcore.compliance.route_prefix'))->middleware((array) config('workcore.compliance.middleware'))->name('api.workcore.v1.')->group(function (): void {
    Route::get('compliance-obligations', [ComplianceController::class, 'index'])->name('compliance-obligations.index');
    Route::post('compliance-requirements', [ComplianceController::class, 'requirement'])->name('compliance-requirements.store');
    Route::post('compliance-obligations', [ComplianceController::class, 'obligation'])->name('compliance-obligations.store');
    Route::post('compliance-obligations/{obligation}/status', [ComplianceController::class, 'obligationStatus'])->name('compliance-obligations.status');
    Route::post('compliance-certificates', [ComplianceController::class, 'certificate'])->name('compliance-certificates.store');
    Route::post('hazards', [ComplianceController::class, 'hazard'])->name('hazards.store');
    Route::post('hazards/{hazard}/status', [ComplianceController::class, 'hazardStatus'])->name('hazards.status');
    Route::post('safety-incidents', [ComplianceController::class, 'incident'])->name('safety-incidents.store');
    Route::post('safety-incidents/{incident}/status', [ComplianceController::class, 'incidentStatus'])->name('safety-incidents.status');
    Route::post('corrective-actions', [ComplianceController::class, 'correctiveAction'])->name('corrective-actions.store');
    Route::post('corrective-actions/{correctiveAction}/work-order', [ComplianceController::class, 'correctiveWorkOrder'])->name('corrective-actions.work-order');
});
