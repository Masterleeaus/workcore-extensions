<?php

return [
    'default_premise_approval_mode' => 'manual',
    'default_document_visibility' => 'private',
    'default_hazard_severity' => 'medium',
    'default_service_window' => 'business_hours',
    'default_service_plan_state' => 'active',
    'visit_reminder_hours' => 24,
    'asset_visibility' => 'internal',
    'access_audit_enabled' => true,
    'meter_reading_required' => false,
    'default_agreement_status' => 'draft',
    'agreement_expiry_warning_days' => 60,
    'default_agreement_finance_provider' => 'titan_money',
    'mask_finance_references_in_untrusted_channels' => true,
    'compliance_warning_days' => 30,
    'compliance_grace_days' => 0,
    'compliance_templates_require_local_verification' => true,
    'compliance_evidence_verification_requires_permission' => true,
    'dashboard_defaults' => [
        'show_health_overview' => true,
        'show_service_readiness' => true,
        'show_qc_summary' => true,
    ],
    'menu_enabled' => true,
];
