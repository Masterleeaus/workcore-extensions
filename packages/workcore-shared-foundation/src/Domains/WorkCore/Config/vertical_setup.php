<?php

declare(strict_types=1);

return [
    'questions' => [
        [
            'key' => 'language.default_locale',
            'section' => 'language',
            'prompt' => 'What is the company default locale?',
            'type' => 'text',
            'required' => true,
            'default' => 'en-AU',
        ],
        [
            'key' => 'language.additional_locales',
            'section' => 'language',
            'prompt' => 'Which additional locales should be enabled?',
            'type' => 'list',
            'required' => false,
            'default' => [],
        ],
        [
            'key' => 'business.lines',
            'section' => 'business_structure',
            'prompt' => 'Which business lines should be created?',
            'type' => 'business_lines',
            'required' => false,
            'default' => [],
        ],
        [
            'key' => 'documents.enabled',
            'section' => 'documents',
            'prompt' => 'Which starter documents should be generated?',
            'type' => 'document_multiselect',
            'required' => false,
            'default' => [],
        ],
        [
            'key' => 'documents.brand_voice',
            'section' => 'documents',
            'prompt' => 'Which writing style should starter documents use?',
            'type' => 'select',
            'required' => false,
            'default' => 'professional',
            'options' => ['professional', 'warm', 'direct', 'formal'],
        ],
    ],
    'document_library' => [
        'cleaning_checklist' => ['type' => 'checklist', 'title' => 'Cleaning Quality Checklist', 'content' => "Confirm scope, safety controls, cleaning tasks, evidence and customer sign-off."],
        'emergency_procedure' => ['type' => 'procedure', 'title' => 'Emergency Procedure', 'content' => "Protect life first, contact emergency services when required, notify the responsible manager and preserve an incident record."],
        'guest_welcome_guide' => ['type' => 'guide', 'title' => 'Guest Welcome Guide', 'content' => "Welcome information, arrival instructions, property rules, support contacts and departure requirements."],
        'incident_report' => ['type' => 'form', 'title' => 'Incident Report', 'content' => "Record the incident, immediate actions, affected people, witnesses, notifications, evidence and follow-up actions."],
        'job_checklist' => ['type' => 'checklist', 'title' => 'Service Job Checklist', 'content' => "Confirm customer, location, scope, hazards, tasks, materials, evidence, defects and completion approval."],
        'maintenance_report' => ['type' => 'report', 'title' => 'Maintenance Report', 'content' => "Record the issue, diagnosis, work completed, materials, asset condition, outstanding risks and recommended follow-up."],
        'participant_service_agreement' => ['type' => 'agreement', 'title' => 'Participant Service Agreement', 'content' => "Define supports, responsibilities, consent, privacy, pricing, cancellations, feedback, incidents and agreement review."],
        'privacy_notice' => ['type' => 'notice', 'title' => 'Privacy Notice', 'content' => "Explain collected information, permitted use, storage, access, disclosure, retention and complaint pathways."],
        'property_owner_report' => ['type' => 'report', 'title' => 'Property Owner Report', 'content' => "Summarise occupancy, maintenance, compliance, financial activity, risks and recommended decisions."],
        'resident_handbook' => ['type' => 'guide', 'title' => 'Resident Handbook', 'content' => "Explain occupancy rules, services, maintenance reporting, safety, privacy, complaints and emergency contacts."],
        'risk_assessment' => ['type' => 'form', 'title' => 'Job Risk Assessment', 'content' => "Identify hazards, affected people, initial risk, controls, residual risk, responsibilities and review triggers."],
        'service_agreement' => ['type' => 'agreement', 'title' => 'Service Agreement', 'content' => "Define scope, service standards, customer responsibilities, pricing, access, variations, cancellations and dispute handling."],
        'staff_induction' => ['type' => 'guide', 'title' => 'Staff Induction', 'content' => "Cover company expectations, safety, conduct, privacy, systems, incident reporting, supervision and role-specific obligations."],
        'trade_compliance_certificate' => ['type' => 'certificate', 'title' => 'Trade Compliance Certificate', 'content' => "Record licensed worker, licence details, regulated work, tests, results, standards, defects and certification statement."],
    ],
];
