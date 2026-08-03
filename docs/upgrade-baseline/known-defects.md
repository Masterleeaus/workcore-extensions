# WorkCore CRM Replacement — Known Defects Baseline

**Branch:** `upgrade/workcore-crm-replacement-foundation`  
**Baseline started:** 2026-08-03  
**Current phase:** Stage 2 P0 hardening and verification

## Severity model

- **P0:** Blocks production rollout or creates a direct security/runtime failure.
- **P1:** Blocks complete CRM/Sales replacement or native MagicAI operation.
- **P2:** Blocks safe migration, upgrade or rollback.
- **P3:** Product maturity or advanced capability gap.

## P0 defects

| ID | Defect | Evidence | Status |
|---|---|---|---|
| P0-001 | Native and host-overlay permission resolvers referenced undefined `WorkCoreAccessLevel::Manage`. | Native resolver, host-overlay resolver and canonical access-level enum. | **Implemented on branch.** Owner/admin now resolve to `All`; source-contract regression added. |
| P0-002 | Native MagicAI APIs inherited standalone `auth:sanctum`, while the MagicAI host uses Passport-compatible `auth:api`. | WorkCore configuration, native wrapper, host routes and direct Finance routes. | **Implemented on branch.** Native wrapper explicitly supplies `auth:api`; standalone WorkCore retains Sanctum; host overlay uses Passport; Sanctum-only direct Finance routes are disabled in MagicAI while governed Finance actions/read models remain available. |
| P0-003 | The active explicit-confirmation verifier accepted any non-empty confirmation ID without binding it to tenant, actor, action, payload, idempotency key, expiry or consumption. | Root provider binding, confirmation verifier and dispatcher transaction order. | **Implemented on branch.** Signed grants are company-, actor-, action-, payload- and idempotency-bound; nonces are one-time; legacy plain tokens are disabled in native MagicAI; consumption occurs inside the business transaction; authenticated grant endpoints enforce entitlement and permission checks. |
| P0-004 | Tenant-owned model reads could remain unscoped when no tenant context was installed. | Canonical and premises `BelongsToCompany` implementations. | **Implemented on branch.** Reads now fail closed; premises traits delegate to the canonical boundary; missing tenant maps to `409 tenant_required`; explicit company-bound membership bootstrap is narrow; company creation and invitation acceptance use durable audited privileged access. |

> These statuses mean the source changes exist on the branch. They are not release-complete until the full Python suite, package build, validator, generated PHP lint and Laravel 10 host matrix pass.

## P1 gaps

- MagicAI host adapter contract remains incomplete.
- Plan and entitlement projection is not implemented.
- Native CRM, Sales, Operations, Property and Workforce navigation is incomplete.
- CRM and Commercial browser workspaces are incomplete.
- Finance still declares itself not production-ready.
- Real authenticated customer-to-payment workflows are not proven inside the complete MagicAI shell.

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
2. Reused the existing signed confirmation grant, signer and nonce infrastructure rather than creating a parallel approval system.
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
6. Added a narrow `queryForExplicitCompany()` bootstrap query that always requires a positive company ID.
7. Routed company creation and invitation acceptance through audited privileged callbacks.
8. Mapped missing tenant exceptions to a stable API `409 tenant_required` response.

## Repository integrity and CI

- Added deterministic package checksum refresh tooling and tests.
- Extended the integrity tool to synchronize parent owned-table metadata from actual migrations.
- Added branch push validation to the native extension build and Laravel 10 fixture workflows.
- Added a branch integrity workflow to refresh checksum manifests and reject drift.
- Native builder tests now derive expected runtime files from source rather than a brittle fixed count.

## Next action

Complete fresh CI verification of all P0 changes. After the verification gate is green, begin Stage 3 with the MagicAI host adapter and plan-entitlement projection.
