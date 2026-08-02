<?php

it('publishes the final canonical WorkCore production candidate', function () {
    $domain = json_decode(file_get_contents(__DIR__.'/../../app/Domains/WorkCore/domain.json'), true, 512, JSON_THROW_ON_ERROR);
    $config = require __DIR__.'/../../app/Domains/WorkCore/config/workcore.php';

    expect($domain['version'])->toBe('7.0.0')
        ->and($domain['status'])->toBe('production-candidate-final')
        ->and($config['version'])->toBe('7.0.0')
        ->and($domain['actions'])->toHaveCount(20)
        ->and($config['actions'])->toHaveCount(20);
});

it('keeps the SQL snapshot aligned with canonical WorkCore tables', function () {
    $sql = file_get_contents(__DIR__.'/../../database_with_data_workcore_merged.sql');
    foreach ([
        'workcore_company_memberships', 'workcore_projects', 'workcore_work_orders',
        'workcore_tasks', 'workcore_appointments', 'workcore_assignments',
        'workcore_time_entries', 'workcore_attendance_records', 'workcore_job_evidence',
        'workcore_recurring_services', 'workcore_idempotency_receipts',
        'workcore_domain_events', 'workcore_audit_records',
    ] as $table) {
        expect($sql)->toContain("CREATE TABLE IF NOT EXISTS `{$table}`");
    }
});
