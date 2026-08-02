# ManagedPremises Upgrade Pass 5 — Access, Condition and Incidents

Version: 1.5.0

## Purpose

This pass adds three premises-owned operational domains without absorbing work that belongs to other Titan components:

- Access assets, credentials, custody and authorisations
- Premise and space condition records
- Premises incidents and external WorkCore links

## Access register

The canonical access register supports physical keys, master keys, access cards, PINs, alarm and gate codes, intercom codes, remotes, mobile credentials, lockboxes and custom access items.

Sensitive values use Laravel's encrypted cast and are hidden during model serialisation. User interfaces show masked values only. Custody events preserve issue, return, loss, recovery, revocation and decommissioning history. Time-bound authorisations cover residents, tenants, storage customers, staff, contractors, visitors and emergency access.

Legacy `pm_property_keys` and `premise_access` records are retained. Their records are copied into the canonical register, and newly created legacy keys are mirrored through `LegacyAccessSynchronizer`.

## Condition records

Condition records cover entry, exit, routine, room, storage-unit, damage, cleanliness, safety, common-area, asset and vacant-readiness observations. They may link to a premise, space, occupancy, agreement, party and comparison record.

ManagedPremises owns physical-condition history. QualityControl continues to own service-quality inspections, rework and corrective action.

## Incidents

Incident types include damage, security, lost keys, noise, access failures, leaks, electrical issues, welfare concerns, pests, dumping, fire safety, injury and complaints.

Privacy levels are standard, sensitive and restricted. Sensitive and restricted records require separate permissions. Generic AI context includes standard incident counts only.

ManagedPremises stores the incident's location, context, privacy, lifecycle and evidence metadata. WorkCore remains authoritative for maintenance requests, work orders, jobs, scheduling and dispatch. Links contain external record identifiers and references only.

## Backward compatibility

- Module name, alias, namespace and existing routes remain unchanged.
- Legacy key and access tables remain available.
- Legacy key screens remain functional but now mask displayed codes.
- Existing service-site, occupancy and agreement functionality remains intact.
- All changes use additive migrations.

## Security boundaries

- Canonical secrets are encrypted at rest.
- Secret values are hidden from serialisation.
- AI context contains counts and redaction markers, never credential values.
- Restricted incidents are excluded unless the user has the explicit restricted-record permission.
- Approved condition records cannot be reopened or edited through lifecycle transitions.
