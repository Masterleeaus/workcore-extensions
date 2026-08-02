<?php

declare(strict_types=1);

$service = static fn (string $name, int $minutes, array $tasks, string $pricing = 'fixed'): array => [
    'name' => $name,
    'default_duration_minutes' => $minutes,
    'pricing_model' => $pricing,
    'tasks' => array_map(static fn (string $task, int $index): array => [
        'key' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $task)),
        'name' => $task,
        'sort_order' => $index,
        'is_required' => true,
    ], $tasks, array_keys($tasks)),
];

return [
    'verticals' => [
        'cleaning' => [
            'service_answer_key' => 'cleaning.service_types',
            'business_line_map' => [
                'domestic' => 'residential_cleaning',
                'commercial' => 'commercial_cleaning',
                'end_of_lease' => 'residential_cleaning',
                'deep_cleaning' => 'residential_cleaning',
                'carpet' => 'residential_cleaning',
                'window' => 'residential_cleaning',
                'short_stay_turnover' => 'residential_cleaning',
                'supported_accommodation' => 'residential_cleaning',
            ],
            'category' => ['key' => 'cleaning', 'name' => 'Cleaning Services'],
            'services' => [
                'domestic' => $service('Domestic Cleaning', 120, ['Confirm scope and access', 'Complete room-by-room clean', 'Record quality evidence', 'Obtain completion sign-off']),
                'commercial' => $service('Commercial Cleaning', 180, ['Complete site induction', 'Clean agreed zones', 'Restock consumables', 'Record exceptions and evidence']),
                'end_of_lease' => $service('End-of-Lease Cleaning', 300, ['Confirm property condition', 'Complete detailed clean', 'Photograph all rooms', 'Record defects and sign-off']),
                'deep_cleaning' => $service('Deep Cleaning', 240, ['Assess surfaces and hazards', 'Complete deep-clean tasks', 'Record before-and-after evidence']),
                'carpet' => $service('Carpet Cleaning', 150, ['Inspect carpet and stains', 'Pre-treat and clean', 'Record treatment and drying advice']),
                'window' => $service('Window Cleaning', 120, ['Assess access and fall risks', 'Clean glass and frames', 'Record completion evidence']),
                'short_stay_turnover' => $service('Short-Stay Turnover', 150, ['Check departure condition', 'Clean and reset property', 'Replace linen and consumables', 'Report damage and readiness']),
                'supported_accommodation' => $service('Supported Accommodation Cleaning', 150, ['Confirm resident-safe access', 'Apply infection and privacy controls', 'Complete clean', 'Record exceptions']),
            ],
            'skills' => [
                ['key' => 'cleaning.general', 'name' => 'General Cleaning', 'category' => 'cleaning', 'requires_certification' => false],
                ['key' => 'cleaning.chemical_safety', 'name' => 'Cleaning Chemical Safety', 'category' => 'safety', 'requires_certification' => true],
            ],
            'conditional_forms' => [[
                'when_key' => 'cleaning.quality_signoff', 'equals' => true,
                'form' => ['key' => 'cleaning_quality_signoff', 'name' => 'Cleaning Quality Sign-off', 'form_type' => 'inspection', 'applies_to' => 'work_order'],
            ]],
        ],
        'pressure_washing' => [
            'service_answer_key' => 'pressure_washing.service_types',
            'business_line_map' => [
                'driveways' => 'exterior_cleaning',
                'paths' => 'exterior_cleaning',
                'building_wash' => 'exterior_cleaning',
                'roof_wash' => 'exterior_cleaning',
                'decks' => 'exterior_cleaning',
                'commercial_surfaces' => 'exterior_cleaning',
                'graffiti_removal' => 'exterior_cleaning',
            ],
            'category' => ['key' => 'pressure_washing', 'name' => 'Pressure and Exterior Cleaning'],
            'services' => [
                'driveways' => $service('Driveway Pressure Cleaning', 120, ['Assess surface and drainage', 'Protect adjacent areas', 'Pressure clean surface', 'Record before-and-after evidence']),
                'paths' => $service('Path and Paving Cleaning', 120, ['Assess surface condition', 'Control runoff', 'Clean paths and paving', 'Record evidence']),
                'building_wash' => $service('Building Wash', 240, ['Assess façade and access', 'Protect openings and services', 'Wash agreed surfaces', 'Inspect and record result']),
                'roof_wash' => $service('Roof Wash', 300, ['Confirm roof-access controls', 'Assess roof condition', 'Complete approved wash method', 'Record defects and evidence']),
                'decks' => $service('Deck Cleaning', 150, ['Assess coating and timber', 'Test cleaning method', 'Clean deck', 'Record outcome']),
                'commercial_surfaces' => $service('Commercial Surface Cleaning', 240, ['Establish exclusion zone', 'Control wastewater', 'Clean surfaces', 'Reopen area after inspection']),
                'graffiti_removal' => $service('Graffiti Removal', 180, ['Identify substrate and coating', 'Test removal method', 'Remove graffiti', 'Record treatment and result']),
            ],
            'skills' => [
                ['key' => 'pressure_washing.surface_assessment', 'name' => 'Pressure-Washing Surface Assessment', 'category' => 'exterior_cleaning', 'requires_certification' => false],
                ['key' => 'pressure_washing.runoff_control', 'name' => 'Wastewater and Runoff Control', 'category' => 'environment', 'requires_certification' => true],
            ],
            'conditional_forms' => [[
                'when_key' => 'pressure_washing.water_recovery', 'equals' => true,
                'form' => ['key' => 'wastewater_control', 'name' => 'Wastewater Control Record', 'form_type' => 'compliance', 'applies_to' => 'work_order'],
            ]],
        ],
        'gardening' => [
            'service_answer_key' => 'gardening.service_types',
            'business_line_map' => [
                'lawn_mowing' => 'garden_maintenance',
                'garden_maintenance' => 'garden_maintenance',
                'hedging' => 'garden_maintenance',
                'weeding' => 'garden_maintenance',
                'green_waste' => 'garden_maintenance',
                'irrigation' => 'garden_maintenance',
                'grounds_maintenance' => 'garden_maintenance',
                'tree_work' => 'garden_maintenance',
            ],
            'category' => ['key' => 'gardening', 'name' => 'Gardening and Grounds'],
            'services' => [
                'lawn_mowing' => $service('Lawn Mowing', 90, ['Inspect grounds and hazards', 'Mow and edge lawns', 'Blow down hard surfaces', 'Record green-waste handling']),
                'garden_maintenance' => $service('Garden Maintenance', 120, ['Inspect garden condition', 'Prune and weed agreed areas', 'Tidy garden beds', 'Record recommendations']),
                'hedging' => $service('Hedge Trimming', 120, ['Assess hedge and access', 'Trim to agreed profile', 'Remove clippings', 'Record completion']),
                'weeding' => $service('Weeding', 90, ['Identify target weeds', 'Apply approved removal method', 'Remove waste', 'Record treatment']),
                'green_waste' => $service('Green-Waste Removal', 60, ['Confirm waste scope', 'Load and secure waste', 'Dispose at approved facility', 'Record disposal']),
                'irrigation' => $service('Irrigation Maintenance', 120, ['Inspect irrigation zones', 'Test operation', 'Repair minor faults', 'Record outstanding defects']),
                'grounds_maintenance' => $service('Grounds Maintenance', 180, ['Inspect site and priorities', 'Complete scheduled grounds tasks', 'Manage waste', 'Record condition and follow-up']),
                'tree_work' => $service('Minor Tree Work', 180, ['Confirm work is within permitted scope', 'Establish exclusion zone', 'Complete minor pruning', 'Escalate arborist work']),
            ],
            'skills' => [
                ['key' => 'gardening.horticulture', 'name' => 'General Horticulture', 'category' => 'gardening', 'requires_certification' => false],
                ['key' => 'gardening.equipment_safety', 'name' => 'Grounds Equipment Safety', 'category' => 'safety', 'requires_certification' => true],
            ],
        ],
        'handyman' => [
            'service_answer_key' => 'handyman.service_types',
            'business_line_map' => [
                'minor_repairs' => 'property_maintenance',
                'assembly' => 'property_maintenance',
                'fixtures' => 'property_maintenance',
                'painting_touchups' => 'property_maintenance',
                'doors_and_locks' => 'property_maintenance',
                'plaster_repairs' => 'property_maintenance',
                'property_make_ready' => 'property_maintenance',
                'preventive_maintenance' => 'property_maintenance',
            ],
            'category' => ['key' => 'handyman', 'name' => 'Handyman and Property Maintenance'],
            'services' => [
                'minor_repairs' => $service('Minor Repairs', 120, ['Confirm scope and licensed-work boundary', 'Assess hazards and materials', 'Complete permitted repair', 'Test and record result']),
                'assembly' => $service('Furniture and Equipment Assembly', 120, ['Check components and instructions', 'Assemble securely', 'Test stability and operation', 'Remove packaging']),
                'fixtures' => $service('Fixture Installation', 120, ['Confirm substrate and services', 'Install non-regulated fixture', 'Test security', 'Record completion']),
                'painting_touchups' => $service('Painting Touch-ups', 180, ['Prepare and protect area', 'Match finish', 'Apply coating', 'Clean and record result']),
                'doors_and_locks' => $service('Door and Lock Maintenance', 120, ['Assess door and hardware', 'Confirm permitted scope', 'Repair or adjust', 'Test access and security']),
                'plaster_repairs' => $service('Minor Plaster Repairs', 180, ['Assess damage and moisture risk', 'Prepare repair', 'Patch and finish', 'Record outstanding cause']),
                'property_make_ready' => $service('Property Make-Ready', 300, ['Inspect property', 'Complete approved minor works', 'Coordinate licensed trades', 'Record readiness and defects']),
                'preventive_maintenance' => $service('Preventive Maintenance Visit', 180, ['Review maintenance schedule', 'Inspect nominated items', 'Complete permitted adjustments', 'Create follow-up defects']),
            ],
            'skills' => [
                ['key' => 'handyman.general_maintenance', 'name' => 'General Property Maintenance', 'category' => 'maintenance', 'requires_certification' => false],
                ['key' => 'handyman.licensed_work_boundary', 'name' => 'Licensed-Work Boundary Recognition', 'category' => 'compliance', 'requires_certification' => true],
            ],
            'conditional_forms' => [[
                'when_key' => 'handyman.licensed_work_boundary', 'equals' => true,
                'form' => ['key' => 'licensed_work_escalation', 'name' => 'Licensed Work Escalation', 'form_type' => 'compliance', 'applies_to' => 'work_order'],
            ]],
        ],
        'plumbing' => [
            'service_answer_key' => 'plumbing.service_types',
            'business_line_map' => [
                'general_plumbing' => 'general_plumbing',
                'drainage' => 'general_plumbing',
                'hot_water' => 'general_plumbing',
                'gas' => 'general_plumbing',
                'roof_plumbing' => 'general_plumbing',
                'maintenance' => 'general_plumbing',
                'emergency' => 'emergency_plumbing',
            ],
            'category' => ['key' => 'plumbing', 'name' => 'Plumbing and Drainage'],
            'services' => [
                'general_plumbing' => $service('General Plumbing', 150, ['Verify licence and scope', 'Diagnose plumbing issue', 'Complete regulated work', 'Test and document outcome']),
                'drainage' => $service('Drainage Service', 180, ['Assess drainage issue', 'Establish environmental controls', 'Clear or repair drainage', 'Test flow and record findings']),
                'hot_water' => $service('Hot-Water Service', 180, ['Identify system and hazards', 'Diagnose fault', 'Complete approved repair', 'Commission and record settings']),
                'gas' => $service('Gas Plumbing Service', 180, ['Verify gas licence class', 'Isolate and test safely', 'Complete regulated work', 'Issue required compliance record']),
                'roof_plumbing' => $service('Roof Plumbing', 240, ['Verify roof-access controls', 'Inspect roof-water system', 'Complete regulated work', 'Test drainage and record evidence']),
                'maintenance' => $service('Plumbing Maintenance', 150, ['Review asset history', 'Inspect system', 'Complete maintenance', 'Update asset and compliance records']),
                'emergency' => $service('Emergency Plumbing Callout', 120, ['Make area safe', 'Stop active damage', 'Complete emergency repair', 'Record cause and follow-up']),
            ],
            'skills' => [
                ['key' => 'plumbing.licensed', 'name' => 'Licensed Plumbing', 'category' => 'trade', 'requires_certification' => true],
                ['key' => 'plumbing.drainage', 'name' => 'Drainage', 'category' => 'trade', 'requires_certification' => true],
            ],
            'forms' => [
                ['key' => 'plumbing_compliance_certificate', 'name' => 'Plumbing Compliance Certificate', 'form_type' => 'certificate', 'applies_to' => 'work_order'],
            ],
            'compliance_requirements' => [
                ['key' => 'plumbing_licence', 'type' => 'licence', 'name' => 'Plumbing Licence', 'worker_required' => true],
                ['when_key' => 'plumbing.compliance_certificates', 'equals' => true, 'requirement' => ['key' => 'plumbing_certificate', 'type' => 'work_order_certificate', 'name' => 'Plumbing Compliance Certificate', 'work_order_required' => true]],
            ],
        ],
        'electrical' => [
            'service_answer_key' => 'electrical.service_types',
            'business_line_map' => [
                'residential' => 'electrical_services',
                'commercial' => 'electrical_services',
                'maintenance' => 'electrical_services',
                'switchboards' => 'electrical_services',
                'lighting' => 'electrical_services',
                'test_and_tag' => 'electrical_services',
                'solar_support' => 'electrical_services',
                'emergency' => 'emergency_electrical',
            ],
            'category' => ['key' => 'electrical', 'name' => 'Electrical Services'],
            'services' => [
                'residential' => $service('Residential Electrical Service', 150, ['Verify licence and isolate safely', 'Diagnose electrical issue', 'Complete regulated work', 'Test and certify']),
                'commercial' => $service('Commercial Electrical Service', 180, ['Complete site isolation and permits', 'Diagnose issue', 'Complete regulated work', 'Test and hand over']),
                'maintenance' => $service('Electrical Maintenance', 150, ['Review asset history', 'Inspect and isolate', 'Complete maintenance', 'Test and update records']),
                'switchboards' => $service('Switchboard Work', 240, ['Verify authority and isolation', 'Inspect switchboard', 'Complete approved work', 'Test, label and certify']),
                'lighting' => $service('Lighting Installation and Repair', 150, ['Confirm design and isolation', 'Install or repair lighting', 'Test operation and protection', 'Record result']),
                'test_and_tag' => $service('Electrical Test and Tag', 120, ['Identify assets', 'Inspect and electrically test', 'Apply status tags', 'Record results and failures']),
                'solar_support' => $service('Solar Electrical Support', 180, ['Verify competency and isolation', 'Inspect solar electrical components', 'Complete approved support work', 'Test and record']),
                'emergency' => $service('Emergency Electrical Callout', 120, ['Make area safe and isolate', 'Diagnose immediate risk', 'Complete emergency repair', 'Test and record follow-up']),
            ],
            'skills' => [
                ['key' => 'electrical.licensed', 'name' => 'Licensed Electrical Work', 'category' => 'trade', 'requires_certification' => true],
                ['key' => 'electrical.test_and_tag', 'name' => 'Electrical Test and Tag', 'category' => 'testing', 'requires_certification' => true],
            ],
            'forms' => [
                ['key' => 'electrical_test_certificate', 'name' => 'Electrical Test Certificate', 'form_type' => 'certificate', 'applies_to' => 'work_order'],
            ],
            'compliance_requirements' => [
                ['key' => 'electrical_licence', 'type' => 'licence', 'name' => 'Electrical Licence', 'worker_required' => true],
                ['when_key' => 'electrical.test_certificates', 'equals' => true, 'requirement' => ['key' => 'electrical_certificate', 'type' => 'work_order_certificate', 'name' => 'Electrical Test Certificate', 'work_order_required' => true]],
            ],
        ],
    ],
];
