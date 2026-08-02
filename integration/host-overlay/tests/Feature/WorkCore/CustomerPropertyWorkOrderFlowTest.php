<?php

declare(strict_types=1);

use App\Services\WorkCore\CustomerPropertyWorkOrderFlow;

it('exposes the canonical customer property work order flow service', function (): void {
    expect(class_exists(CustomerPropertyWorkOrderFlow::class))->toBeTrue();
});

it('registers the governed business flow endpoint', function (): void {
    $routes = file_get_contents(base_path('routes/api.php'));
    expect($routes)->toContain('/flows/customer-property-work-order');
});
