<?php

return [
    'premise_operations' => [
        'schema' => 'managedpremises.panel.v1',
        'title' => 'Premise operations',
        'permission' => 'managedpremises.operations.view',
        'components' => ['metric_grid', 'alert_list', 'workflow_list'],
        'actions' => ['open_operations', 'prepare_workflow'],
    ],
    'vacancy_board' => [
        'schema' => 'managedpremises.panel.v1',
        'title' => 'Vacancy board',
        'permission' => 'managedpremises.vacancies.view',
        'components' => ['metric_grid', 'listing_cards', 'publication_status'],
        'actions' => ['open_vacancies', 'prepare_listing', 'request_approval'],
    ],
    'mobile_workflows' => [
        'schema' => 'managedpremises.panel.v1',
        'title' => 'Mobile workflows',
        'permission' => 'managedpremises.mobile.workflows.view',
        'components' => ['workflow_cards', 'stage_progress', 'due_badges'],
        'actions' => ['advance_stage', 'pause_workflow', 'open_record'],
    ],
    'portal_summary' => [
        'schema' => 'managedpremises.panel.v1',
        'title' => 'Portal access',
        'permission' => 'managedpremises.portal.manage',
        'components' => ['grant_summary', 'capability_badges', 'expiry_list'],
        'actions' => ['issue_portal_link', 'revoke_grant'],
    ],
    'owner_portfolio' => [
        'schema' => 'managedpremises.panel.v1',
        'title' => 'Owner and portfolio overview',
        'permission' => 'managedpremises.ownership.view',
        'components' => ['ownership_summary', 'portfolio_metrics', 'approval_list', 'authority_status'],
        'actions' => ['open_ownership', 'open_approvals', 'prepare_approval_request'],
    ],
];
