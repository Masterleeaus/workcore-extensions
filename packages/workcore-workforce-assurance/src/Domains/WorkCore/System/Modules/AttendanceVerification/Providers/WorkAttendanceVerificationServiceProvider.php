<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Providers;

use Illuminate\Support\ServiceProvider;

final class WorkAttendanceVerificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('workcore.modules.attendance_verification.status', [
            'mode' => 'domain_schema_foundation',
            'runtime_enabled' => false,
            'description' => 'Verification policy and evidence schemas are present; verification adapters remain opt-in.',
        ]);
    }

    public function boot(): void {}
}
