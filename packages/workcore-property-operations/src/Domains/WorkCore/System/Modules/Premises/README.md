# ManagedPremises

ManagedPremises is the physical-premises domain for Titan BOS. It supports service sites, rooming houses, residential and commercial real estate, storage facilities, warehouses, office centres, community housing, mixed-use premises, and custom verticals.

## Current canonical models

- `Property`: top-level managed premise and profile.
- `PremiseSpace`: hierarchical building, level, room, unit, shed, locker, suite, bay, zone, or shared area.
- `PremisePartyRole`: a person or organisation acting in a premise or space role.
- `PremiseOccupancy`: application, reservation, occupancy, departure, and transfer history.
- `PremiseAgreement`: residency, lease, storage, licence, management, and other agreement lifecycle records.
- `PremiseAgreementParty`: agreement-specific party roles and signing state.
- `PremiseAgreementFinanceLink`: references to Titan Money or external financial records without duplicating accounting.
- `PremiseComplianceRulePack`: versioned, source-attributed requirement templates that require explicit review and application.
- `PremiseComplianceRequirement`: premise or space obligations with jurisdiction, source, recurrence, responsibility, and evidence expectations.
- `PremiseComplianceOccurrence`: immutable dated instances of recurring requirements.
- `PremiseComplianceEvidence`: document-linked evidence with expiry and authorised review state.

## Compatibility models

`PropertyUnit`, `PropertyRoom`, and `PropertyContact` remain available for existing installations. New vertical features should use the canonical models above.

## Domain boundaries

ManagedPremises owns physical location context, spaces, parties, occupancy, access, condition, compliance, incidents, assets, availability, and premises documents.

WorkCore owns requests, jobs, work orders, scheduling, dispatch, and field execution. Titan Money owns charges, deposits, bonds, invoices, payments, arrears, and reconciliation. Quality Control owns service-quality inspections and corrective action.

See `Docs/UPGRADE_PASS6_COMPLIANCE_REPORT.md` for the current release details. Compliance records track operational status; they do not establish legal compliance or replace qualified advice.
