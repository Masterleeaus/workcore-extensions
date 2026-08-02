<?php

it('publishes paired lifecycle actions and durable governance capabilities', function () {
    $manifest = require __DIR__.'/../../app/Domains/WorkCore/config/workcore.php';

    expect($manifest['actions'])
        ->toContain('work_order.transition')
        ->toContain('task.transition')
        ->toContain('dispatch.unassign')
        ->toContain('time_entry.stop')
        ->toContain('attendance.clock_out')
        ->toContain('evidence.verify');

    expect($manifest['capabilities'])
        ->toContain('durable_idempotency')
        ->toContain('domain_events')
        ->toContain('audit_records');
});
