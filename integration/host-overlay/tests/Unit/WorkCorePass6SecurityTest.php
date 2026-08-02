<?php

test('pass 6 runtime security hardening is present', function () {
    $root = dirname(__DIR__, 2);
    $middleware = file_get_contents($root.'/app/Domains/WorkCore/Http/Middleware/ResolveWorkCoreContext.php');
    $dispatcher = file_get_contents($root.'/app/Domains/WorkCore/Actions/BusinessActionDispatcher.php');
    $recurrence = file_get_contents($root.'/app/Domains/WorkCore/Actions/RecurringServiceRunDueAction.php');

    expect($middleware)->toContain('workcore.scope.select');
    expect($middleware)->toContain('Branch selection permission is required.');
    expect($dispatcher)->toContain("expires_at?->isPast()");
    expect($dispatcher)->toContain("'completed_at' => null");
    expect($recurrence)->toContain('array_intersect_key');
    expect($recurrence)->not->toContain('WorkOrder::create([...$template,...$c->tenancyAttributes()');
});
