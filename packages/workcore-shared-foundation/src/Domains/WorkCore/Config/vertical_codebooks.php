<?php

declare(strict_types=1);

return [
    'roster.type' => [
        'default' => 'operations',
        'values' => [
            'operations' => 'Operations', 'office' => 'Office', 'property_management' => 'Property management',
            'inspection' => 'Inspection', 'maintenance' => 'Maintenance', 'cleaning' => 'Cleaning service',
            'contractor' => 'Contractor', 'compliance' => 'Compliance', 'on_call' => 'On call',
            'emergency' => 'Emergency', 'leasing' => 'Leasing', 'open_home' => 'Open home',
            'rooming_house' => 'Rooming house', 'storage_facility' => 'Storage facility',
            'pressure_washing' => 'Pressure washing', 'gardening' => 'Gardening and grounds',
            'handyman' => 'Handyman service', 'plumbing' => 'Plumbing service', 'electrical' => 'Electrical service',
            'housekeeping' => 'Housekeeping', 'front_desk' => 'Front desk', 'room_turnover' => 'Room turnover',
            'guest_services' => 'Guest services', 'student_support' => 'Student support',
            'participant_support' => 'Participant support', 'community_support' => 'Community support',
            'night_audit' => 'Night audit', 'facilities' => 'Facilities', 'security' => 'Security',
            'groundskeeping' => 'Groundskeeping',
        ],
    ],
    'roster.shift_type' => [
        'default' => 'operations',
        'values' => [
            'operations' => 'Operations', 'office' => 'Office', 'property_management' => 'Property management',
            'inspection' => 'Inspection', 'maintenance' => 'Maintenance', 'cleaning' => 'Cleaning service',
            'contractor' => 'Contractor', 'compliance' => 'Compliance', 'on_call' => 'On call',
            'emergency' => 'Emergency', 'leasing' => 'Leasing', 'open_home' => 'Open home',
            'rooming_house' => 'Rooming house', 'storage_facility' => 'Storage facility',
            'pressure_washing' => 'Pressure washing', 'gardening' => 'Gardening and grounds',
            'handyman' => 'Handyman service', 'plumbing' => 'Plumbing service', 'electrical' => 'Electrical service',
            'housekeeping' => 'Housekeeping', 'front_desk' => 'Front desk', 'room_turnover' => 'Room turnover',
            'guest_services' => 'Guest services', 'student_support' => 'Student support',
            'participant_support' => 'Participant support', 'community_support' => 'Community support',
            'night_audit' => 'Night audit', 'facilities' => 'Facilities', 'security' => 'Security',
            'groundskeeping' => 'Groundskeeping',
        ],
    ],
    'worker.type' => [
        'default' => 'employee',
        'values' => [
            'employee' => 'Employee', 'contractor' => 'Contractor', 'volunteer' => 'Volunteer',
            'casual' => 'Casual', 'agent' => 'Agent', 'property_manager' => 'Property manager',
            'maintenance' => 'Maintenance worker', 'inspector' => 'Inspector', 'cleaner' => 'Cleaner',
            'gardener' => 'Gardener', 'groundskeeper' => 'Groundskeeper', 'handyman' => 'Handyman',
            'plumber' => 'Plumber', 'electrician' => 'Electrician', 'apprentice' => 'Apprentice',
            'support_worker' => 'Support worker', 'accommodation_worker' => 'Accommodation worker',
            'housekeeper' => 'Housekeeper', 'front_desk' => 'Front-desk worker', 'concierge' => 'Concierge',
        ],
    ],
    'work_order.priority' => [
        'default' => 'normal',
        'values' => ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent', 'emergency' => 'Emergency'],
    ],
    'work_order.status' => [
        'default' => 'draft',
        'values' => [
            'draft' => 'Draft', 'ready' => 'Ready', 'in_progress' => 'In progress',
            'paused' => 'Paused', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
        ],
    ],
    'appointment.type' => [
        'default' => 'job',
        'values' => [
            'job' => 'Job', 'inspection' => 'Inspection', 'viewing' => 'Viewing', 'maintenance' => 'Maintenance',
            'meeting' => 'Meeting', 'move_in' => 'Move in', 'move_out' => 'Move out', 'compliance' => 'Compliance',
            'cleaning' => 'Cleaning', 'pressure_washing' => 'Pressure washing', 'gardening' => 'Gardening',
            'handyman' => 'Handyman', 'plumbing' => 'Plumbing', 'electrical' => 'Electrical',
            'room_turnover' => 'Room turnover', 'guest_service' => 'Guest service',
            'participant_support' => 'Participant support', 'other' => 'Other',
        ],
    ],
    'dispatch.assignment_type' => [
        'default' => 'job',
        'values' => [
            'job' => 'Job', 'maintenance' => 'Maintenance', 'inspection' => 'Inspection', 'viewing' => 'Viewing',
            'room_turnover' => 'Room turnover', 'move_in' => 'Move in', 'move_out' => 'Move out',
            'compliance' => 'Compliance', 'contractor_callout' => 'Contractor callout', 'delivery' => 'Delivery',
            'cleaning' => 'Cleaning', 'pressure_washing' => 'Pressure washing', 'gardening' => 'Gardening',
            'handyman' => 'Handyman', 'plumbing' => 'Plumbing', 'electrical' => 'Electrical',
            'participant_support' => 'Participant support', 'emergency' => 'Emergency', 'other' => 'Other',
        ],
    ],
];
