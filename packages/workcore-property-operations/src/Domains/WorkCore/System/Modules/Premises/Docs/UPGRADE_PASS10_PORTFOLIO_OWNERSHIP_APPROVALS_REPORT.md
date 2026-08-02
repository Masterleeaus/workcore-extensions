# ManagedPremises Upgrade Pass 10 — Portfolios, Ownership, Management Authorities and Owner Approvals

Version: 2.0.0  
Date: 2026-07-22

## Purpose

Pass 10 adds the portfolio and stakeholder layer required by rooming-house operators, real-estate managers, storage businesses, community housing providers and multi-site operators.

## Delivered

- Nested premise portfolios with dated membership history.
- One active primary portfolio membership per premise, plus secondary, reporting and investment groupings.
- Ownership interests linked to canonical premise party roles.
- Optional ownership percentages with an active-total ceiling of 100 percent.
- Management-authority records with approval-gated activation, notice, suspension and terminal history.
- Canonical approval requests with append-only decisions and configurable approval counts.
- Legacy `pm_property_approvals` backfill and ongoing compatibility synchronisation.
- Owner, landlord, investor and authorised-manager portal capabilities.
- Portfolio and owner generative-panel summaries with protected finance boundaries.

## Boundaries

- Ownership and management-authority records are operational metadata, not legal advice or proof of title.
- Source documents remain in the ManagedPremises document vault.
- Titan Money owns balances, spending limits, invoices, payments, statements and transactions.
- WorkCore owns maintenance and project execution.
- Approval records authorise a proposal; they do not execute linked work or money movements.
- Owner portal views exclude access credentials, tenant contact data, restricted incidents and raw financial identifiers.

## Compatibility

No existing table is removed. Existing `PropertyApproval` routes and records remain available. Existing approvals are mapped to the canonical approval register using `legacy_property_approval_id`.
