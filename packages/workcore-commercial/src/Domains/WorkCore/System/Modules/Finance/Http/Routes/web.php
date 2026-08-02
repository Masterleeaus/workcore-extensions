<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Finance\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');
