<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;

it('boots the canonical WorkCore registries inside the standalone host', function (): void {
    expect(app()->bound(BusinessActionRegistry::class))->toBeTrue()
        ->and(app()->bound(CapabilityRegistry::class))->toBeTrue()
        ->and(app()->bound(ReadModelRegistry::class))->toBeTrue()
        ->and(app()->bound(WorkModuleRegistry::class))->toBeTrue();
});

it('registers the expected WorkCore runtime catalogue', function (): void {
    $modules = app(WorkModuleRegistry::class);
    $actions = app(BusinessActionRegistry::class);
    $capabilities = app(CapabilityRegistry::class);
    $readModels = app(ReadModelRegistry::class);

    expect(count($modules->all()))->toBeGreaterThanOrEqual(30)
        ->and(count($actions->all()))->toBeGreaterThanOrEqual(200)
        ->and(count($capabilities->all()))->toBeGreaterThanOrEqual(45)
        ->and(count($readModels->all()))->toBeGreaterThanOrEqual(70);
});
