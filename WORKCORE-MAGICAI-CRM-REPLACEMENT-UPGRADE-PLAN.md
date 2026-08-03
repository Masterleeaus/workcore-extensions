# WorkCore → MagicAI CRM and Sales Replacement

## Multi-Step Upgrade and Cutover Plan

**Repository:** `Masterleeaus/workcore-extensions`  
**Working branch:** `upgrade/workcore-crm-replacement-foundation`  
**Status:** Active  
**Date started:** 2026-08-03

## Objective

Upgrade the WorkCore extension suite into the production-ready, MagicAI-native replacement for:

- MagicAI CRM
- MagicAI Sales
- Generic CRM projects and tasks
- CRM calendar and scheduling
- Operational quoting, invoicing and payments

MagicAI remains responsible for authentication, SaaS plans and subscriptions, AI engines, AI Chat, AI Agents, Marketplace installation, themes and platform administration.

WorkCore becomes authoritative for:

- Customers and contacts
- Leads and opportunities
- Sales pipelines and CRM activities
- Quotes, invoices, payments and receivables
- Work orders, appointments, scheduling and dispatch
- Premises, assets and documents
- Workforce, attendance, credentials and compliance

The native package set remains:

```text
WorkCore
WorkCoreBusinessNetwork
WorkCoreCommercial
WorkCoreWorkOperations
WorkCorePropertyOperations
WorkCoreWorkforceAssurance
```

The parent package owns the canonical runtime and migrations. The five add-ons activate exact module groups without duplicating canonical source or migrations.

---

# Non-negotiable architecture rules

1. WorkCore is the only CRM authority after cutover.
2. MagicAI CRM and Sales must not remain parallel record owners.
3. Existing MagicAI CRM data may be imported once, but permanent two-way synchronisation is prohibited.
4. A WorkCore operating `Company` is the tenant business.
5. A CRM customer organisation maps to a WorkCore `Customer`, never the tenant `Company`.
6. WorkCore Finance owns invoice, payment, receivable, credit and reconciliation truth.
7. MagicAI may render WorkCore information but must not create a second ledger.
8. All tenant-owned reads and writes must fail closed when company context is missing.
9. High-risk business actions must use verifiable, expiring, one-time approvals.
10. No production rollout occurs until real authenticated, multi-company workflows pass end-to-end testing.

---

# Stage 1 — Freeze and baseline the current release

## Work

Record:

- Current `main` commit
- Six package manifests
- Provider map
- Module ownership map
- Migration inventory
- Action registry inventory
- Read-model registry inventory
- API and web route inventory
- Current test results
- Package checksums
- Known security defects

Create:

```text
docs/upgrade-baseline/
├── modules.json
├── actions.json
├── read-models.json
├── routes.json
├── migrations.json
├── permissions.json
├── package-checksums.json
└── known-defects.md
```

## Gate

- All six packages build deterministically.
- Current tests run from a clean checkout.
- Every source file has a package owner.
- No duplicated migrations or canonical runtime source.
- Baseline evidence is committed before broad feature work.

---

# Stage 2 — Repair critical runtime and security defects

This stage blocks all later feature work.

## 2.1 Correct the permission-level defect

The native permission resolver returns `WorkCoreAccessLevel::Manage`, but the enum does not define `Manage`.

### Upgrade

- Add a failing regression test first.
- Map tenant owners and authorised administrators to the intended valid level, expected to be `All` unless the complete authorisation model proves otherwise.
- Audit all access-level comparisons and serializers.
- Add exhaustive role-to-access-level tests.

### Tests

- Owner
- Administrator
- Manager
- Worker
- Added-record access
- Owned-record access
- No-access behaviour
- Unknown-role behaviour

## 2.2 Replace Sanctum assumptions with MagicAI authentication

### Upgrade

Introduce:

```text
WorkCoreAuthenticationAdapterContract
└── MagicAiPassportAuthenticationAdapter
```

Move API middleware to host configuration and use MagicAI Passport-compatible authentication, normally `auth:api`.

### Tests

- Valid token
- Expired token
- Revoked token
- Missing token
- User without active company
- Multi-company user
- Deactivated membership
- Cross-company attempt

## 2.3 Replace the confirmation bypass

Create a persistent approval record containing:

```text
approval_id
company_id
requested_by
approved_by
action_key
payload_hash
risk_level
status
requested_at
approved_at
expires_at
consumed_at
revoked_at
metadata
```

Validate company, actor, approver, action, canonical payload hash, risk, status, expiry, revocation and consumption. Consume the approval in the same transaction as the protected action.

### Tests

- Valid approval
- Wrong company
- Wrong actor
- Wrong action
- Modified payload
- Expired or revoked approval
- Replay
- Concurrent double execution
- Transaction rollback

## 2.4 Make tenant reads fail closed

### Upgrade

- Throw before querying when tenant context is absent.
- Require explicit tenant context in jobs, listeners, commands, schedules, imports and AI tools.
- Provide an explicit audited privileged cross-company query API.
- Prohibit silent global-scope removal.

### Tests

- HTTP, queue, scheduler, CLI, listener and AI calls without tenant context
- Explicit privileged query
- Cross-company lookup rejection

## Stage 2 gate

- Invalid enum reference removed.
- Passport-authenticated requests pass.
- Approval replay is impossible.
- Missing tenant context blocks tenant-owned reads and writes.
- Multi-company isolation passes.

---

# Stage 3 — Complete the MagicAI host contract

Create stable adapters for:

- Authentication and current user
- Company membership
- Roles and permissions
- Plans and subscriptions
- Extension activation
- Menus and dashboards
- Notifications
- Files and storage
- Queues and schedules
- Audit events
- AI action exposure

Extend identity context to include:

```text
actor_subject
worker_id
branch_id
territory_id
device_id
authentication_assurance
security_revision
membership_revision
```

Create a revision-aware entitlement projection:

```text
MagicAI effective subscription
→ WorkCore company entitlements
→ capability revision
→ route, action, menu and UI enforcement
```

Suggested entitlement keys:

```text
workcore.crm
workcore.catalogue
workcore.finance
workcore.payments
workcore.operations
workcore.scheduling
workcore.dispatch
workcore.property
workcore.workforce
workcore.compliance
```

## Gate

- WorkCore permissions derive from authenticated MagicAI identity.
- Active-company selection is durable and validated.
- Activation and entitlement are separate concepts.
- Disabled capabilities disappear from routes, menus, actions and AI tools.

---

# Stage 4 — Complete CRM replacement parity

`WorkCoreBusinessNetwork` is authoritative for customers, contacts, leads, pipelines, opportunities, CRM activities and forecast.

## Customer and contact management

Complete:

- Individual and organisation customers
- Multiple contacts and contact roles
- Communication preferences
- Tags, segments, statuses and notes
- Files
- Duplicate detection and merge
- Retention and anonymisation

## Lead management

Complete:

- Capture and source attribution
- Ownership and territory assignment
- Qualification, score and status history
- Follow-up dates and activities
- Duplicate detection
- Conversion and conversion rollback policy

## Pipelines and opportunities

Complete:

- Multiple configurable pipelines
- Stage ordering, probability and forecast values
- Expected close dates
- Pipeline board and activity timeline
- Won/lost reasons
- Stale-opportunity detection
- Opportunity-to-quote and opportunity-to-work-order conversion

## CRM activities

Support calls, email, SMS, meetings, site visits, notes, tasks, follow-ups, customer messages and AI recommendations linked to all relevant WorkCore records.

## Gate

```text
Lead
→ Qualification
→ Opportunity
→ Pipeline movement
→ Quote request
→ Won outcome
→ Customer
→ Premises
→ Work order
```

must complete without MagicAI CRM.

---

# Stage 5 — Complete Sales and Finance replacement parity

`WorkCoreCommercial` is authoritative for quotes, invoices, payments, receivables, credits and operational accounting.

## Quotes

Complete templates, catalogue lines, labour, materials, equipment, travel, taxes, discounts, optional and recurring lines, revisions, internal approval, customer acceptance, rejection, expiry, signature and conversion to work orders.

Recommended lifecycle:

```text
Draft estimate
→ Reviewed quote
→ Sent proposal
→ Accepted commercial scope
```

## Invoices

Complete draft, quote-based, completed-job, progress, deposit and recurring invoices, credit notes, partial payments, allocations, overpayments, refunds, voids, write-offs, collections, receipts and tax reporting.

## Payments

Support the Titan Zero priority:

```text
Cash
PayID
Bank transfer
PayPal card checkout
```

Complete requests, QR instructions, evidence upload, bank import, automatic and manual matching, partial matching, confidence thresholds, webhook idempotency, reconciliation and receipts.

## Accounting integrity

Verify balanced journals, closed periods, GST, rounding, immutable issued documents, credit constraints, reversals, reconciliation locking, concurrency and complete audit trails.

## Gate

```text
Opportunity
→ Quote
→ Customer acceptance
→ Work order
→ Completed job
→ Invoice
→ Payment request
→ Match
→ Receipt
→ Reconciliation
```

must complete with WorkCore as sole authority.

---

# Stage 6 — Replace CRM projects, tasks and calendar

Use WorkCore-native concepts:

| Generic CRM | WorkCore |
|---|---|
| Project | Work order, contract or recurring service |
| Task | Work-order task, checklist or corrective action |
| Milestone | Work-order stage or service milestone |
| Calendar event | Appointment |
| Assignment | Dispatch assignment |
| Timesheet | Time entry and attendance evidence |
| Project file | Work-order, premises or customer document |
| Project discussion | Activity timeline or operational conversation |

Complete opportunity/quote conversion, customer-to-premises linkage, scheduling, dispatch, recurring services, assignments, forms, evidence, time entries, defects, repairs, sign-off and invoice readiness.

## Gate

There must be no functional reason to retain MagicAI CRM projects, tasks or calendar.

---

# Stage 7 — Build native WorkCore navigation and workspaces

Use stable UI roots:

```text
Operations
Customers
Workforce
Resources
Commercial
```

Build:

- Titan Flow manager home
- CRM workspaces
- Quote, invoice and payment workspaces
- Schedule and dispatch surfaces
- Property and asset surfaces
- Workforce and compliance surfaces
- Stable field-worker interface
- Persistent AI interaction routed through governed WorkCore actions

## Gate

CRM, Sales and operational work completes through native MagicAI/Titan Zero interfaces without legacy CRM screens.

---

# Stage 8 — Build the one-time CRM migration system

## Extract

Companies, contacts, leads, deals, pipelines, activities, projects, tasks, events, products, proposals, estimates, invoices, payments, attachments, notes, custom fields and owners.

## Transform

```text
MagicAI CRM Company → WorkCore Customer
MagicAI Contact → WorkCore Contact
MagicAI Lead → WorkCore Lead
MagicAI Deal → WorkCore Opportunity
MagicAI Pipeline → WorkCore Sales Pipeline
MagicAI Activity → WorkCore CRM Activity
MagicAI Project → WorkCore Work Order or Contract
MagicAI Task → WorkCore Task or Activity
MagicAI Event → WorkCore Appointment
MagicAI Product → WorkCore Catalogue Item
MagicAI Proposal/Estimate → WorkCore Quote
MagicAI Invoice → WorkCore Invoice
MagicAI Payment → WorkCore Payment Allocation
```

Preserve source identity and checksums in migration mapping tables. Route ambiguous, orphaned or inconsistent records to reconciliation.

## Gate

- Every source record is imported, deliberately skipped or reconciled.
- Financial totals match.
- Tenant companies are never imported as customers.
- Migration reruns idempotently.
- Rollback is tested.

---

# Stage 9 — Harden installation and upgrades

Add:

- Signed packages and trusted keys
- Install and upgrade locks
- Staging and preflight validation
- Dependency and host compatibility checks
- Backups
- Atomic promotion
- Install, migration and asset journals
- Health checks
- Automatic rollback and recovery
- Dependency-safe uninstall
- Data-retention policy
- Reconciled installed/running/healthy state

## Gate

A failed upgrade cannot leave partially installed code, partially migrated schema or stale provider caches.

---

# Stage 10 — Production verification matrix

Test layers:

- Unit
- Database integration
- Host contracts
- End-to-end business journeys
- Compatibility matrix

Mandatory end-to-end journeys include lead-to-customer, quote acceptance, work-order scheduling, field completion, invoice generation, payment reconciliation, recurring work, compliance blocking, cross-company rejection, secure approval, migration, upgrade and rollback.

## Gate

All critical journeys pass inside the real MagicAI host with real authentication and persistence.

---

# Stage 11 — Controlled pilot

Pilot with:

- Internal tenant
- New business without migration
- Existing business with migration
- Field worker
- Manager
- Administrator

Monitor authentication, tenancy, permissions, approvals, queues, offline conflicts, duplicate customers, quote/invoice/payment exceptions, performance and usability.

## Gate

No unresolved critical security defect, cross-company exposure, ledger imbalance, data loss or core workflow failure.

---

# Stage 12 — Cut over and decommission MagicAI CRM/Sales

1. Back up database and files.
2. Put legacy CRM/Sales into read-only mode.
3. Record source counts and financial totals.
4. Run final incremental migration.
5. Reconcile.
6. Enable WorkCore entitlements and menus.
7. Disable legacy writes.
8. Redirect legacy URLs where appropriate.
9. Monitor critical workflows.
10. Preserve legacy data read-only through the rollback window.
11. Remove legacy providers after rollback expiry.
12. Remove legacy schema only in a later separately approved release.

Rollback immediately for cross-company exposure, missing financial data, ledger imbalance, widespread authentication failure, inability to operate jobs, payment corruption or migration-map corruption.

---

# Branch sequence

```text
upgrade/workcore-crm-replacement-foundation
fix/workcore-p0-runtime-security
feature/workcore-magicai-host-contract
feature/workcore-crm-parity
feature/workcore-commercial-parity
feature/workcore-operations-integration
feature/workcore-native-navigation
feature/workcore-crm-importer
feature/workcore-installer-hardening
test/workcore-production-matrix
release/workcore-crm-replacement-v1
```

Each pull request must include implementation, tests, migration notes, upgrade notes, rollback notes, updated documentation and verification evidence.

---

# Priority order

## P0

- Permission enum defect
- MagicAI Passport authentication
- Real approval verification
- Fail-closed tenancy
- Cross-company isolation tests
- Authenticated action tests

## P1

- Host adapters
- Entitlement projection
- CRM parity
- Quote/invoice/payment parity
- Operations integration
- Native menus and workspaces

## P2

- CRM importer
- Reconciliation
- Transactional upgrades
- Rollback
- Production matrix

## P3

- Titan Flow
- Field-worker PWA
- Offline conflict management
- Advanced forecasting
- AI-assisted operations
- Presentations and documents
- Analytics

---

# Definition of complete

The upgrade is complete only when:

- WorkCore is the sole CRM and Sales authority.
- MagicAI identity and plans control access correctly.
- Tenant isolation fails closed everywhere.
- High-risk actions use secure one-time approvals.
- The full customer-to-payment lifecycle works.
- Existing CRM data migrates and reconciles safely.
- Native desktop and field interfaces are usable.
- Extension upgrades are atomic and reversible.
- Production tests pass inside the real MagicAI host.
- MagicAI CRM and Sales can be disabled without loss of required capability.

---

# Progress log

## 2026-08-03

- Created isolated upgrade branch.
- Added this root upgrade plan.
- Began Stage 1 baseline and Stage 2.1 permission defect investigation.
