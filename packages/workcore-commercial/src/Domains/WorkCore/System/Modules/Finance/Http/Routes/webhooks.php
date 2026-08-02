<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Finance\Http\Controllers\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('paypal/{connectionKey}', PayPalWebhookController::class)->name('paypal');
