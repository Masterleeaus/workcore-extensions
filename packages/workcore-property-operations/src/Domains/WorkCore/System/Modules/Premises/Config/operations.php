<?php

$moveInStages = [
    ['key' => 'application', 'label' => 'Application or enquiry reviewed'],
    ['key' => 'space_offer', 'label' => 'Space selected and offered'],
    ['key' => 'agreement', 'label' => 'Agreement prepared and signed'],
    ['key' => 'finance_reference', 'label' => 'Deposit, bond, or billing references linked'],
    ['key' => 'condition', 'label' => 'Entry condition recorded'],
    ['key' => 'access', 'label' => 'Access issued'],
    ['key' => 'occupancy', 'label' => 'Occupancy activated'],
];

$moveOutStages = [
    ['key' => 'notice', 'label' => 'Notice or departure recorded'],
    ['key' => 'exit_booking', 'label' => 'Exit date and inspection scheduled'],
    ['key' => 'access_return', 'label' => 'Keys and credentials returned or revoked'],
    ['key' => 'condition', 'label' => 'Exit condition recorded'],
    ['key' => 'work_handoff', 'label' => 'Required work handed to WorkCore'],
    ['key' => 'finance_reference', 'label' => 'Final finance references linked'],
    ['key' => 'vacancy', 'label' => 'Occupancy ended and space released'],
];

$turnoverStages = [
    ['key' => 'vacated', 'label' => 'Space confirmed vacant'],
    ['key' => 'condition', 'label' => 'Condition and damage reviewed'],
    ['key' => 'work_handoff', 'label' => 'Cleaning or maintenance handed to WorkCore'],
    ['key' => 'compliance', 'label' => 'Compliance holds reviewed'],
    ['key' => 'access_reset', 'label' => 'Access reset or reissued'],
    ['key' => 'ready', 'label' => 'Space marked available'],
];

return [
    'default_profile' => 'generic',
    'profile_aliases' => [
        'community_housing' => 'rooming_house',
        'office_centre' => 'commercial_property',
        'strata_property' => 'residential_rental',
        'warehouse' => 'storage_facility',
        'mixed_use' => 'generic',
        'custom' => 'generic',
    ],
    'profiles' => [
        'service_site' => [
            'label' => 'Service Site Operations',
            'dashboard' => [
                'cards' => [
                    ['metric' => 'active_service_plans', 'label' => 'Active service plans'],
                    ['metric' => 'upcoming_visits', 'label' => 'Visits in 30 days'],
                    ['metric' => 'open_incidents', 'label' => 'Open site incidents'],
                    ['metric' => 'workcore_linked_incidents', 'label' => 'WorkCore handoffs'],
                    ['metric' => 'overdue_compliance', 'label' => 'Overdue compliance'],
                    ['metric' => 'overdue_access_returns', 'label' => 'Overdue access returns'],
                    ['metric' => 'pending_condition_records', 'label' => 'Condition records pending'],
                    ['metric' => 'active_workflows', 'label' => 'Active workflows'],
                ],
                'alerts' => ['compliance_holds', 'overdue_access_returns', 'access_failures'],
            ],
            'workflows' => [
                'site_onboarding' => [
                    'label' => 'Service site onboarding', 'subject_type' => 'premise',
                    'stages' => [
                        ['key' => 'site_profile', 'label' => 'Site profile and contacts confirmed'],
                        ['key' => 'spaces', 'label' => 'Service areas recorded'],
                        ['key' => 'access', 'label' => 'Access requirements confirmed'],
                        ['key' => 'hazards', 'label' => 'Hazards and compliance reviewed'],
                        ['key' => 'service_plan', 'label' => 'Service plan activated'],
                        ['key' => 'ready', 'label' => 'Site marked service ready'],
                    ],
                ],
                'access_readiness_review' => [
                    'label' => 'Access readiness review', 'subject_type' => 'premise',
                    'stages' => [
                        ['key' => 'instructions', 'label' => 'Access instructions reviewed'],
                        ['key' => 'credentials', 'label' => 'Keys and credentials checked'],
                        ['key' => 'authorisations', 'label' => 'Staff or contractor authorisations checked'],
                        ['key' => 'exceptions', 'label' => 'Access exceptions recorded'],
                        ['key' => 'ready', 'label' => 'Access readiness confirmed'],
                    ],
                ],
                'service_issue_handoff' => [
                    'label' => 'Service issue handoff', 'subject_type' => 'incident',
                    'stages' => [
                        ['key' => 'triage', 'label' => 'Issue triaged'],
                        ['key' => 'evidence', 'label' => 'Premise evidence linked'],
                        ['key' => 'workcore', 'label' => 'Work handed to WorkCore'],
                        ['key' => 'status', 'label' => 'WorkCore status reviewed'],
                        ['key' => 'close', 'label' => 'Premise issue closed'],
                    ],
                ],
            ],
        ],
        'rooming_house' => [
            'label' => 'Rooming House Operations',
            'dashboard' => [
                'cards' => [
                    ['metric' => 'occupied_spaces', 'label' => 'Occupied rooms'],
                    ['metric' => 'available_spaces', 'label' => 'Vacant rooms'],
                    ['metric' => 'upcoming_departures', 'label' => 'Departures in 30 days'],
                    ['metric' => 'agreements_expiring', 'label' => 'Agreements ending in 60 days'],
                    ['metric' => 'open_incidents', 'label' => 'Open incidents'],
                    ['metric' => 'overdue_compliance', 'label' => 'Overdue compliance'],
                    ['metric' => 'overdue_access_returns', 'label' => 'Overdue key returns'],
                    ['metric' => 'turnover_spaces', 'label' => 'Rooms in turnover'],
                ],
                'alerts' => ['capacity_warnings', 'unassigned_residents', 'missing_active_agreements', 'restricted_incidents', 'compliance_holds'],
            ],
            'workflows' => [
                'resident_move_in' => ['label' => 'Resident move-in', 'subject_type' => 'occupancy', 'stages' => $moveInStages],
                'resident_move_out' => ['label' => 'Resident move-out', 'subject_type' => 'occupancy', 'stages' => $moveOutStages],
                'room_turnover' => ['label' => 'Room turnover', 'subject_type' => 'space', 'stages' => $turnoverStages],
                'routine_room_inspection' => [
                    'label' => 'Routine room inspection', 'subject_type' => 'space',
                    'stages' => [
                        ['key' => 'schedule', 'label' => 'Inspection scheduled'],
                        ['key' => 'notice', 'label' => 'Required notice recorded'],
                        ['key' => 'condition', 'label' => 'Condition record completed'],
                        ['key' => 'incident_or_work', 'label' => 'Issues linked to incident or WorkCore'],
                        ['key' => 'close', 'label' => 'Inspection workflow closed'],
                    ],
                ],
            ],
        ],
        'residential_rental' => [
            'label' => 'Residential Real Estate Operations',
            'dashboard' => [
                'cards' => [
                    ['metric' => 'occupied_spaces', 'label' => 'Occupied tenancies'],
                    ['metric' => 'available_spaces', 'label' => 'Vacancies'],
                    ['metric' => 'agreements_expiring', 'label' => 'Leases ending in 60 days'],
                    ['metric' => 'upcoming_departures', 'label' => 'Upcoming departures'],
                    ['metric' => 'open_incidents', 'label' => 'Open property issues'],
                    ['metric' => 'workcore_linked_incidents', 'label' => 'Issues sent to WorkCore'],
                    ['metric' => 'overdue_compliance', 'label' => 'Overdue compliance'],
                    ['metric' => 'pending_condition_records', 'label' => 'Condition records pending'],
                ],
                'alerts' => ['vacancies', 'agreements_without_documents', 'missing_active_agreements', 'compliance_holds'],
            ],
            'workflows' => [
                'tenant_move_in' => ['label' => 'Tenant move-in', 'subject_type' => 'occupancy', 'stages' => $moveInStages],
                'tenant_move_out' => ['label' => 'Tenant move-out', 'subject_type' => 'occupancy', 'stages' => $moveOutStages],
                'vacancy_turnover' => ['label' => 'Vacancy turnover', 'subject_type' => 'space', 'stages' => $turnoverStages],
                'lease_renewal' => [
                    'label' => 'Lease renewal', 'subject_type' => 'agreement',
                    'stages' => [
                        ['key' => 'review', 'label' => 'Current agreement reviewed'],
                        ['key' => 'instructions', 'label' => 'Owner or manager instructions recorded'],
                        ['key' => 'offer', 'label' => 'Renewal offered'],
                        ['key' => 'signature', 'label' => 'Replacement agreement signed'],
                        ['key' => 'finance_reference', 'label' => 'Billing references checked'],
                        ['key' => 'activate', 'label' => 'Replacement agreement activated'],
                    ],
                ],
            ],
        ],
        'commercial_property' => [
            'label' => 'Commercial Real Estate Operations',
            'dashboard' => [
                'cards' => [
                    ['metric' => 'occupied_spaces', 'label' => 'Occupied tenancies'],
                    ['metric' => 'available_spaces', 'label' => 'Available tenancies'],
                    ['metric' => 'agreements_expiring', 'label' => 'Leases ending in 90 days'],
                    ['metric' => 'open_incidents', 'label' => 'Open incidents'],
                    ['metric' => 'workcore_linked_incidents', 'label' => 'WorkCore handoffs'],
                    ['metric' => 'overdue_compliance', 'label' => 'Overdue obligations'],
                    ['metric' => 'active_access_authorisations', 'label' => 'Active access authorisations'],
                    ['metric' => 'turnover_spaces', 'label' => 'Tenancies in turnover'],
                ],
                'alerts' => ['vacancies', 'agreements_without_documents', 'compliance_holds', 'overdue_access_returns'],
            ],
            'workflows' => [
                'business_move_in' => ['label' => 'Business move-in', 'subject_type' => 'occupancy', 'stages' => $moveInStages],
                'business_move_out' => ['label' => 'Business move-out', 'subject_type' => 'occupancy', 'stages' => $moveOutStages],
                'tenancy_turnover' => ['label' => 'Tenancy turnover', 'subject_type' => 'space', 'stages' => $turnoverStages],
                'commercial_lease_renewal' => [
                    'label' => 'Commercial lease renewal', 'subject_type' => 'agreement',
                    'stages' => [
                        ['key' => 'critical_dates', 'label' => 'Critical dates reviewed'],
                        ['key' => 'instructions', 'label' => 'Owner instructions recorded'],
                        ['key' => 'terms', 'label' => 'Proposed terms recorded'],
                        ['key' => 'document', 'label' => 'Replacement document linked'],
                        ['key' => 'signature', 'label' => 'Parties signed'],
                        ['key' => 'activate', 'label' => 'Replacement agreement activated'],
                    ],
                ],
            ],
        ],
        'storage_facility' => [
            'label' => 'Storage Facility Operations',
            'dashboard' => [
                'cards' => [
                    ['metric' => 'occupied_spaces', 'label' => 'Occupied units'],
                    ['metric' => 'available_spaces', 'label' => 'Available units'],
                    ['metric' => 'reserved_spaces', 'label' => 'Reserved units'],
                    ['metric' => 'occupancy_rate', 'label' => 'Occupancy rate'],
                    ['metric' => 'agreements_expiring', 'label' => 'Agreements ending in 30 days'],
                    ['metric' => 'overdue_access_returns', 'label' => 'Overdue access returns'],
                    ['metric' => 'open_incidents', 'label' => 'Open access or damage incidents'],
                    ['metric' => 'turnover_spaces', 'label' => 'Units in turnover'],
                ],
                'alerts' => ['vacancies', 'unassigned_storage_customers', 'missing_active_agreements', 'access_failures', 'compliance_holds'],
            ],
            'workflows' => [
                'storage_customer_move_in' => ['label' => 'Storage customer move-in', 'subject_type' => 'occupancy', 'stages' => $moveInStages],
                'storage_customer_move_out' => ['label' => 'Storage customer move-out', 'subject_type' => 'occupancy', 'stages' => $moveOutStages],
                'storage_unit_turnover' => ['label' => 'Storage unit turnover', 'subject_type' => 'space', 'stages' => $turnoverStages],
                'access_exception_review' => [
                    'label' => 'Access exception review', 'subject_type' => 'incident',
                    'stages' => [
                        ['key' => 'triage', 'label' => 'Access issue triaged'],
                        ['key' => 'identity', 'label' => 'Authorised party confirmed'],
                        ['key' => 'credential', 'label' => 'Credential or key status reviewed'],
                        ['key' => 'incident', 'label' => 'Incident and evidence updated'],
                        ['key' => 'restore', 'label' => 'Access restored or revoked'],
                        ['key' => 'close', 'label' => 'Review closed'],
                    ],
                ],
            ],
        ],
        'generic' => [
            'label' => 'Premises Operations',
            'dashboard' => [
                'cards' => [
                    ['metric' => 'occupied_spaces', 'label' => 'Occupied spaces'],
                    ['metric' => 'available_spaces', 'label' => 'Available spaces'],
                    ['metric' => 'open_incidents', 'label' => 'Open incidents'],
                    ['metric' => 'overdue_compliance', 'label' => 'Overdue compliance'],
                    ['metric' => 'active_workflows', 'label' => 'Active workflows'],
                    ['metric' => 'turnover_spaces', 'label' => 'Spaces in turnover'],
                ],
                'alerts' => ['vacancies', 'compliance_holds', 'overdue_access_returns'],
            ],
            'workflows' => [
                'premise_onboarding' => [
                    'label' => 'Premise onboarding', 'subject_type' => 'premise',
                    'stages' => [
                        ['key' => 'profile', 'label' => 'Premise profile confirmed'],
                        ['key' => 'spaces', 'label' => 'Spaces configured'],
                        ['key' => 'parties', 'label' => 'Responsible parties linked'],
                        ['key' => 'access', 'label' => 'Access arrangements reviewed'],
                        ['key' => 'compliance', 'label' => 'Compliance register reviewed'],
                        ['key' => 'ready', 'label' => 'Premise operationally ready'],
                    ],
                ],
                'space_turnover' => ['label' => 'Space turnover', 'subject_type' => 'space', 'stages' => $turnoverStages],
                'incident_follow_up' => [
                    'label' => 'Incident follow-up', 'subject_type' => 'incident',
                    'stages' => [
                        ['key' => 'triage', 'label' => 'Incident triaged'],
                        ['key' => 'actions', 'label' => 'Actions and ownership confirmed'],
                        ['key' => 'workcore', 'label' => 'Required work linked to WorkCore'],
                        ['key' => 'review', 'label' => 'Outcome reviewed'],
                        ['key' => 'close', 'label' => 'Workflow closed'],
                    ],
                ],
            ],
        ],
    ],
];
