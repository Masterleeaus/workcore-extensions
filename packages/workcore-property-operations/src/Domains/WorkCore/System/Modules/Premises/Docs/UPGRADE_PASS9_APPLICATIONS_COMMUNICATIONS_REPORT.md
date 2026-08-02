# ManagedPremises Upgrade Pass 9 — Applications, Communications, Notices and Self-Service

Version: 1.9.0  
Date: 2026-07-22

## Purpose

Pass 9 adds vacancy enquiries, an applicant pipeline, secure applicant progress links, portal-visible communications, approval-controlled notices, and resident/tenant/customer self-service requests.

## Boundaries

- Enquiries and applications are not occupancy records.
- Only an accepted application may be explicitly converted by an authorised operator.
- Conversion creates a party role and a reserved, pending, or active occupancy; it does not create a legal agreement or financial transaction.
- ManagedPremises owns request context and history. WorkCore owns maintenance jobs, work orders, scheduling, dispatch and completion.
- Notices are operational records, not legal advice. A notice must be approved before issue.
- Internal and restricted messages never enter applicant or standard portal output.

## Data added

- `pm_premise_vacancy_enquiries`
- `pm_premise_applications`
- `pm_premise_application_events`
- `pm_premise_conversations`
- `pm_premise_messages`
- `pm_premise_notices`
- `pm_premise_self_service_requests`

## Security

- Applicant portal tokens contain at least 256 bits of entropy and are stored only as SHA-256 hashes.
- Applicant assessment notes are hidden from serialisation and external portals.
- Message visibility is explicit: portal, internal, or restricted.
- External forms are throttled and require privacy consent.
- Portal request records expose status and operational details but not WorkCore internal identifiers.

## Compatibility

All previous premises, vacancy, portal, occupancy, agreement, access, condition, incident, compliance and workflow records remain unchanged. No existing table is removed or renamed.

## Security donor integration supplement

During Pass 9, `Modules for Titan BOS/Security.zip` was deep-scanned and its physical access-card and work-permit concepts were extracted into ManagedPremises. The resulting canonical tables, workflows and ownership boundaries are documented in `Docs/SECURITY_MODULE_CONCEPT_EXTRACTION_REPORT.md`.
