<?php

return [
    'name' => 'Premises',

    /*
     * Pass 35 Stage D — domain authority flags.
     *
     * WorkCore owns job, contact, checklist and asset identity. The premises
     * domain holds typed links only. Inspections are QUARANTINED as
     * non-authoritative pending the TitanQuality ruling: existing records
     * remain readable, but no new premises-owned inspection may be created
     * while 'inspections_authoritative' is false.
     */
    'authority' => [
        'jobs_owned_here' => false,          // canonical: tz_work_orders
        'contacts_owned_here' => false,      // canonical: tz_customer_contacts
        'inspections_authoritative' => false, // quarantined pending TitanQuality ruling
        'checklists_authoritative' => false,  // migrating to WorkCore Forms
        'assets_authoritative' => false,      // migrating to WorkCore Assets
    ],

    // company is the authoritative tenant boundary; company_and_user preserves the legacy creator-only view.
    'tenant_scope_mode' => 'company',

    // If enabled, show a quick "Create Job" link on site pages (only if the core Jobs/Projects module exists).
    'visit_generation' => [
        'default_days' => 30,
        'max_per_plan' => 60,
    ],

    'integrations' => [
        'jobs' => true,
        'documents' => true,
        'titan_zero' => false,
        'quality_control_owner' => true,
        'titan_money' => true,
        'workcore' => true,
        'aichatpro_generative_panels' => true,
        'mobile_workflows' => true,
        'portal_links' => true,
        'vacancy_channels' => true,
    ],

    'ownership' => [
        'premises_master' => 'managedpremises',
        'inspection_engine' => 'quality_control',
        'financial_transactions' => 'titan_money',
        'premise_condition' => 'managedpremises',
        'maintenance_execution' => 'workcore',
        'compliance_register' => 'managedpremises',
        'legal_interpretation' => 'customer_or_qualified_adviser',
    ],

    'defaults' => [
        'approval_mode' => 'manual',
        'hazard_severity' => 'medium',
        'service_window' => 'business_hours',
        'service_plan_state' => 'active',
        'agreement_status' => 'draft',
        'agreement_expiry_warning_days' => 60,
        'agreement_finance_provider' => 'titan_money',
        'access_item_status' => 'available',
        'condition_status' => 'draft',
        'incident_status' => 'open',
        'compliance_requirement_status' => 'active',
        'compliance_warning_days' => 30,
        'compliance_grace_days' => 0,
    ],
];
