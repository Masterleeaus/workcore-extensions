<?php

declare(strict_types=1);

return [
    'requirement_types' => [
        'licence' => ['label' => 'Worker licence or credential', 'satisfied_by' => 'licence'],
        'permit' => ['label' => 'Permit or authority approval', 'satisfied_by' => 'permit'],
        'safety_control' => ['label' => 'Safety or isolation control', 'satisfied_by' => 'safety_control'],
        'test_result' => ['label' => 'Inspection or test result', 'satisfied_by' => 'test_result'],
        'evidence' => ['label' => 'Required job evidence', 'satisfied_by' => 'evidence'],
        'certificate' => ['label' => 'Completion or compliance certificate', 'satisfied_by' => 'certificate'],
    ],
    'evidence_phases' => ['before', 'during', 'after', 'defect', 'certificate', 'warranty'],
    'safety_control_types' => [
        'site_induction', 'hazard_assessment', 'ppe', 'lockout_tagout', 'electrical_isolation',
        'water_isolation', 'gas_isolation', 'fall_protection', 'confined_space', 'chemical_control',
        'traffic_control', 'public_exclusion', 'plant_isolation', 'environmental_control',
    ],
    'templates' => [
        'electrical' => [
            'requirements' => [
                ['code' => 'electrical_worker_licence', 'type' => 'licence', 'label' => 'Active electrical worker licence', 'required' => true],
                ['code' => 'electrical_isolation', 'type' => 'safety_control', 'label' => 'Electrical isolation and lockout recorded', 'required' => true],
                ['code' => 'electrical_test_results', 'type' => 'test_result', 'label' => 'Required electrical test results recorded', 'required' => true],
                ['code' => 'electrical_after_evidence', 'type' => 'evidence', 'label' => 'Completed-work evidence captured', 'required' => true],
            ],
            'warranty_days' => 365,
        ],
        'plumbing' => [
            'requirements' => [
                ['code' => 'plumbing_worker_licence', 'type' => 'licence', 'label' => 'Active plumbing licence or registration', 'required' => true],
                ['code' => 'water_isolation', 'type' => 'safety_control', 'label' => 'Water or service isolation recorded', 'required' => true],
                ['code' => 'pressure_leak_test', 'type' => 'test_result', 'label' => 'Pressure or leak test recorded where applicable', 'required' => true],
                ['code' => 'plumbing_after_evidence', 'type' => 'evidence', 'label' => 'Completed-work evidence captured', 'required' => true],
            ],
            'warranty_days' => 365,
        ],
        'cleaning' => [
            'requirements' => [
                ['code' => 'cleaning_hazard_assessment', 'type' => 'safety_control', 'label' => 'Hazards and chemical controls checked', 'required' => true],
                ['code' => 'cleaning_before_evidence', 'type' => 'evidence', 'label' => 'Before-service evidence captured', 'required' => false],
                ['code' => 'cleaning_after_evidence', 'type' => 'evidence', 'label' => 'After-service evidence captured', 'required' => true],
            ],
            'warranty_days' => 7,
        ],
        'gardening' => [
            'requirements' => [
                ['code' => 'gardening_hazard_assessment', 'type' => 'safety_control', 'label' => 'Site and plant hazards checked', 'required' => true],
                ['code' => 'gardening_public_exclusion', 'type' => 'safety_control', 'label' => 'Public exclusion or traffic control recorded where needed', 'required' => false],
                ['code' => 'gardening_after_evidence', 'type' => 'evidence', 'label' => 'Completed-work evidence captured', 'required' => true],
            ],
            'warranty_days' => 14,
        ],
        'handyman' => [
            'requirements' => [
                ['code' => 'handyman_scope_check', 'type' => 'safety_control', 'label' => 'Scope checked for licensed-trade exclusions', 'required' => true],
                ['code' => 'handyman_after_evidence', 'type' => 'evidence', 'label' => 'Completed-work evidence captured', 'required' => true],
            ],
            'warranty_days' => 90,
        ],
        'pressure_washing' => [
            'requirements' => [
                ['code' => 'pressure_washing_hazard_assessment', 'type' => 'safety_control', 'label' => 'Surface, public and chemical hazards checked', 'required' => true],
                ['code' => 'pressure_washing_environmental_control', 'type' => 'safety_control', 'label' => 'Runoff and environmental controls recorded', 'required' => true],
                ['code' => 'pressure_washing_before_evidence', 'type' => 'evidence', 'label' => 'Before-service evidence captured', 'required' => true],
                ['code' => 'pressure_washing_after_evidence', 'type' => 'evidence', 'label' => 'After-service evidence captured', 'required' => true],
            ],
            'warranty_days' => 14,
        ],
    ],
];
