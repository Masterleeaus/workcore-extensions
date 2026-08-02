<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Forms\Http\Controllers\FormsController;
use Illuminate\Support\Facades\Route;
Route::prefix((string) config('workcore.forms.route_prefix'))->middleware((array) config('workcore.forms.middleware'))->name('api.workcore.v1.')->group(function (): void {
    Route::get('form-templates', [FormsController::class, 'templates'])->name('form-templates.index');
    Route::post('form-templates', [FormsController::class, 'storeTemplate'])->name('form-templates.store');
    Route::post('form-templates/{template}/publish', [FormsController::class, 'publishTemplate'])->name('form-templates.publish');
    Route::get('form-submissions', [FormsController::class, 'submissions'])->name('form-submissions.index');
    Route::post('form-submissions', [FormsController::class, 'start'])->name('form-submissions.store');
    Route::put('form-submissions/{submission}/responses', [FormsController::class, 'saveResponse'])->name('form-submissions.responses.store');
    Route::post('form-submissions/{submission}/submit', [FormsController::class, 'submit'])->name('form-submissions.submit');
    Route::post('form-failures/{failure}/work-order', [FormsController::class, 'convertFailure'])->name('form-failures.work-order');
});
