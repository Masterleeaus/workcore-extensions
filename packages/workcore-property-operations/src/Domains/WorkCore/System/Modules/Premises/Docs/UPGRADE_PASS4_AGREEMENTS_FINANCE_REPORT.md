# ManagedPremises Upgrade Pass 4 — Agreements and Finance Boundaries

Version: 1.4.0

## Purpose

This pass adds a canonical agreement register for rooming houses, residential and commercial real estate, storage facilities, office centres, community housing, mixed-use premises, and other profiles that enable agreements.

ManagedPremises owns agreement identity and operational state. It does not own accounting transactions.

## Canonical records

### PremiseAgreement

Stores:

- agreement type and reference
- premise, space, and occupancy relationships
- draft, offer, signature, activation, notice, ending, expiry, termination, cancellation, and supersession state
- start, end, notice, signature, and termination dates
- renewal method and notice period
- direct document-vault relationship, external document reference, and concise operational terms summary
- source and renewal lineage

### PremiseAgreementParty

Stores agreement-specific parties and roles without duplicating the authoritative CRM identity:

- provider, owner, manager
- resident, tenant, licensee, storage customer, or business occupier
- guarantor, representative, witness, billing contact, or other role
- signing state and signature timestamp

### PremiseAgreementFinanceLink

Stores provider-neutral external references for:

- billing accounts
- recurring charges
- deposits and bonds
- invoices and payments
- arrears cases
- credit notes, refunds, and other supported financial records

The link record stores no calculated balance, ledger entry, invoice line, payment allocation, bond transaction, or arrears calculation.

## Finance ownership boundary

- ManagedPremises: agreement, location, space, occupancy, parties, dates, document link, external finance references.
- Titan Money: recurring charges, deposits, bonds, invoices, payments, credits, refunds, arrears, reconciliation, and accounting state.
- External finance provider: authoritative when the link provider is `external`.

## Lifecycle behavior

- New agreements begin as draft, offered, pending signature, or active.
- Terminal agreement history cannot be reactivated.
- Renewals create a new record linked through `previous_agreement_id`.
- An active replacement supersedes the previous current agreement.
- Draft renewals do not prematurely terminate the current agreement.
- Signed parties and activated agreement parties are retained as history.
- Finance links are deactivated rather than deleted from operational history. Legacy occupancy references are cleared or replaced so deactivated links are not silently reactivated.

## Backward compatibility

Existing occupancy fields remain:

- `agreement_reference`
- `billing_account_reference`
- `deposit_reference`

The migration imports existing values into canonical agreements and finance links. `LegacyAgreementSynchronizer` keeps new legacy occupancy writes compatible during occupancy creation, state changes, and transfers.

Existing module identity, namespaces, property routes, occupancy routes, unit/room compatibility models, and customer data remain intact.

## Security and AI restrictions

AI capabilities may summarise agreement counts, lifecycle state, expiry windows, and finance-link status. They must not:

- create or recalculate financial transactions
- treat agreement metadata as legal advice
- activate or renew agreements without user approval
- expose finance identifiers in untrusted channels
- rewrite terminal agreement or occupancy history

## Verification

Standalone contracts cover agreement states and permitted finance-link types. Structural checks cover migrations, models, routes, permissions, lifecycle ownership, signals, AI restrictions, UI registration, and legacy synchronisation.

## Permission hardening

Pass 4 also reconciles the route permission set with the module permission manifest and adds an additive permission-backfill migration. Existing role choices are retained; company admin roles receive any newly missing ManagedPremises permissions.
