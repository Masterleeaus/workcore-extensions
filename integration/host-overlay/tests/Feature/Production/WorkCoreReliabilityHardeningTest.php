<?php

use Illuminate\Support\Facades\File;

it('keeps public maintenance routes removed and scopes governed APIs', function (): void {
    $web = File::get(base_path('routes/web.php'));
    $api = File::get(base_path('routes/api.php'));

    expect($web)
        ->not->toContain('Artisan::call')
        ->not->toContain('/system/catch-clear')
        ->not->toContain('/system/storage-link');

    expect($api)
        ->toContain("throttle:workcore-actions")
        ->toContain("throttle:titan-proposals")
        ->toContain("throttle:offline-sync")
        ->toContain("company.active")
        ->toContain("workcore.tenant");
});

it('contains stale-claim recovery for the outbox and offline operations', function (): void {
    $publisher = File::get(app_path('Domains/WorkCore/System/Outbox/DatabaseOutboxPublisher.php'));
    $sync = File::get(app_path('Services/Sync/OfflineOperationService.php'));

    expect($publisher)
        ->toContain('recoverStaleClaims')
        ->toContain('processing_timeout_seconds')
        ->toContain("status' => 'retry'");

    expect($sync)
        ->toContain('payload_sha256')
        ->toContain('lockForUpdate')
        ->toContain('Offline operation ID was reused with different action data.')
        ->toContain('Offline operation is already processing.');
});
