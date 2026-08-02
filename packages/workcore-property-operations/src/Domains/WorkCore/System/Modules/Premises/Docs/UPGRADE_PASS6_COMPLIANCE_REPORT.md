# ManagedPremises Upgrade Pass 6 — Compliance Register and Rule Packs

Version: 1.6.0

## Added

- Company-scoped, versioned compliance rule packs with country, region, locality, source, effective dates, and human verification provenance.
- Explicitly unverified bundled starter templates for service sites, rooming houses, residential property, and storage facilities.
- Premise and space-level requirements with responsible parties, recurrence, warning windows, grace periods, evidence expectations, and source references.
- Immutable dated occurrences with pending, due, overdue, completed, waived, and not-applicable lifecycle states.
- Evidence records linked to the document vault, expiry dates, submission state, and authorised review.
- Manual rule-pack application with an explicit acknowledgement gate for unverified templates.
- Compliance dashboard and premise overview counts.
- `managedpremises:refresh-compliance` command for scheduled due-state refresh.

## Boundary

The module records operational obligations and evidence. It does not determine legal applicability, provide legal advice, or claim that a premise is compliant. Rule packs never auto-activate, and unverified packs require an explicit user acknowledgement before application.
