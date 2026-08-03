# WorkCore CRM Replacement — Upgrade Baseline

**Branch:** `upgrade/workcore-crm-replacement-foundation`  
**Baseline started:** 2026-08-03  
**Current phase:** Stage 3 MagicAI host contract and plan-entitlement projection

## Severity model

- **P0:** Blocks production rollout or creates a direct security/runtime failure.
- **P1:** Blocks complete CRM/Sales replacement or native MagicAI operation.
- **P2:** Blocks safe migration, upgrade or rollback.
- **P3:** Product maturity or advanced capability gap.

## P0 defects

| ID | Defect | Evidence | Status |
|---|---|---|---|
| P0-001 | Native and host-overlay permission resolvers referenced undefined `WorkCoreAccessLevel::Manage`. | Native resolver, host-overlay resolver and canonical access-level enum. | **Implemented and regression-tested.** Owner/admin now resolve to `All`. |
| P0-002 | Native MagicAI APIs inherited standalone `auth:sanctum`, while the MagicAI host uses Passport-compatible `auth:api`. | WorkCore configuration, native wrapper, host routes and direct Finance routes. | **Implemented and compatibility-tested.** Native MagicAI uses `auth:api`; standalone WorkCore retains Sanctum; direct Sanctum-only Finance routes are disabled in MagicAI while governed Finance actions/read models remain available. |
| P0-003 | The active explicit-confirmation verifier accepted any non-empty confirmation ID without binding it to tenant, actor, action, payload, idempotency key, expiry or consumption. | Root provider binding, confirmation verifier and dispatcher transaction order. | **Implemented and regression-tested.** Signed grants are company-, actor-, action-, payload- and idempotency-bound; nonces are expiring and one-time; legacy plain tokens are disabled; consumption occurs inside the business transaction. |
| P0-004 | Tenant-owned model reads could remain unscoped when no tenant context was installed. | Canonical and premises `BelongsToCompany` implementations. | **Implemented and regression-tested.** Reads fail closed; premises traits delegate to the canonical boundary; missing tenant maps to `409 tenant_required`; membership bootstrap is restricted to one trusted company-bound adapter; pre-tenant company workflows require durable audited privileged access. |

## P1 host-contract work

| ID | Capability | Status |
|---|---|---|
| P1-001 | Provider-neutral effective subscription snapshot | **Implemented.** Normalizes subscription ID, plan ID, status, plan features, validity window and source revision without embedding a payment provider. |
| P1-002 | Company entitlement projection | **Implemented.** Every registered capability receives an explicit projected state; source input is canonicalized and hashed; identical snapshots do not create a new revision. |
| P1-003 | Atomic entitlement revisions | **Implemented.** A deterministic state row is inserted before `lockForUpdate()`, so first and later projections serialize through the same transaction boundary. |
| P1-004 | Fail-closed entitlement resolver | **Implemented.** Unknown and optional unprojected capabilities are denied; only declared bootstrap capabilities remain available before projection. |
| P1-005 | MagicAI plan feature contract | **Implemented.** The native migration safely adds seven WorkCore plan flags, including core, operations, workforce, resources, commercial, offline and AI actions. |
| P1-006 | MagicAI subscription reconciliation | **Implemented.** Native resolver, refresh service, single/all-company command and scheduled reconciliation are registered. Scheduled runs use overlap and single-server locks. |
| P1-007 | Complete capability mapping | **Implemented.** All registered WorkCore capabilities are covered exactly once by bootstrap or at least one of the six WorkCore entitlement groups. |

## Remaining P1 gaps

- Native CRM, Sales, Operations, Property and Workforce navigation remains incomplete.
- CRM and Commercial browser workspaces remain incomplete.
- Finance still declares itself not production-ready.
- Real authenticated customer-to-payment workflows are not yet proven inside the complete production MagicAI shell.
- Plan projection has source-contract and package validation coverage; database concurrency and live MagicAI subscription-shape fixtures still need full integration tests.

## P2 gaps

- One-time MagicAI CRM/Sales importer is not implemented.
- Migration reconciliation and idempotent external-ID mapping are not implemented.
- Package signing, transactional staging, atomic promotion, health validation and automatic rollback are incomplete.

## Verification log

### P0-001 — permission resolution

1. Added `tests/test_workcore_permission_resolver_contract.py` before production changes.
2. Confirmed both resolver copies referenced an enum case that does not exist.
3. Confirmed the canonical resolver uses `WorkCoreAccessLevel::All` for privileged membership.
4. Replaced `Manage` with `All` in both resolver copies.

### P0-002 — MagicAI authentication

1. Added `tests/test_workcore_magicai_auth_contract.py` before production changes.
2. Traced middleware through standalone configuration, native wrapper, host overlay and Finance route registration.
3. Preserved standalone Sanctum compatibility.
4. Added Passport middleware configuration to the native parent.
5. Updated host-overlay APIs to `auth:api`.
6. Disabled direct Sanctum-only Finance routes inside MagicAI; governed WorkCore Finance APIs remain active.

### P0-003 — explicit confirmation

1. Added `tests/test_workcore_confirmation_security_contract.py` before production changes.
2. Reused the signed confirmation grant, signer and nonce infrastructure instead of creating a second approval system.
3. Split confirmation into `verify()` and transactional `consume()` phases.
4. Bound grants to company, actor, action, payload hash and idempotency key.
5. Disabled legacy human confirmation tokens in the native package.
6. Added authenticated confirmation-grant endpoints with action, entitlement and permission validation.
7. Increased confirmation token validation length to support signed grants.

### P0-004 — fail-closed tenancy

1. Added `tests/test_workcore_fail_closed_tenancy_contract.py` before production changes.
2. Changed the canonical company global scope to throw when tenant context is absent.
3. Unified both premises tenancy traits with the canonical boundary.
4. Added `PrivilegedTenantAccessContract` and a scoped implementation requiring actor and reason.
5. Added durable `tz_privileged_tenant_access_audits` records and structured logs.
6. Removed the generic model-level scope bypass.
7. Restricted pre-tenant membership validation to `MagicAIUserCompanyAdapter`, with mandatory company, user and active-status predicates.
8. Routed company creation and invitation acceptance through audited privileged callbacks.
9. Mapped missing tenant exceptions to a stable API `409 tenant_required` response.

### P1-001 to P1-007 — plan entitlements

1. Added provider-neutral `EffectiveSubscriptionSnapshot` and resolver contract.
2. Added a MagicAI subscription resolver using configurable table and column identifiers, status normalization and plan feature extraction.
3. Added revisioned company state and per-capability projection tables.
4. Added a complete projection writer using canonical source checksums and transactional first-write locking.
5. Added a resolver exposing both `allows()` and a company capability revision without exposing raw subscription records.
6. Added six WorkCore feature groups plus the offline host flag to MagicAI plans.
7. Added `workcore:refresh-entitlements` for one company or all active companies.
8. Added configurable scheduled reconciliation with `withoutOverlapping()` and `onOneServer()`.
9. Added contract tests ensuring every registered WorkCore capability is represented in the native feature map or bootstrap set.

## Repository integrity and CI

- Added deterministic package checksum refresh tooling and regression tests.
- Package manifests are validated in check-only mode on pull requests.
- Repository validation reports six packages, 35 modules and 2,158 ownership-tracked source files with no ownership errors.
- Native builder tests derive runtime ownership from source instead of frozen file counts.
- PHP lint inventory is derived from checksum manifests rather than a hard-coded count.
- Previous branch heads passed repository validation, package checksums, full package PHP lint, MagicAI 11 compatibility and native six-ZIP build/validation.
- The final entitlement-hardening head must still complete its fresh six-workflow CI matrix before this phase is marked release-verified.

## Next action

1. Complete the fresh CI matrix for the entitlement-hardening head.
2. Add database-backed subscription and concurrent-projection integration fixtures.
3. Begin native menu/navigation and browser workspace implementation.
