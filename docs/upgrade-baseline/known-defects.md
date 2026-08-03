# WorkCore CRM Replacement — Known Defects Baseline

**Branch:** `upgrade/workcore-crm-replacement-foundation`  
**Baseline started:** 2026-08-03

## Severity model

- **P0:** Blocks production rollout or creates a direct security/runtime failure.
- **P1:** Blocks complete CRM/Sales replacement or native MagicAI operation.
- **P2:** Blocks safe migration, upgrade or rollback.
- **P3:** Product maturity or advanced capability gap.

## P0 defects

| ID | Defect | Evidence | Status |
|---|---|---|---|
| P0-001 | Native and host-overlay permission resolvers referenced undefined `WorkCoreAccessLevel::Manage`. | `native-extensions/WorkCore/System/Resolvers/WorkCorePermissionResolver.php`; `integration/host-overlay/app/Support/WorkCore/WorkCorePermissionResolver.php`; canonical enum defines `All`, `Added`, `Owned`, `Both`, `None`. | **Fixed on branch** with a red-green source-contract regression test. Full repository CI still required. |
| P0-002 | WorkCore API defaults and a Finance route group assume `auth:sanctum`, while the inspected MagicAI host contract uses Passport-compatible `auth:api`. | WorkCore configuration and Finance service provider route registration. | Open — next investigation. |
| P0-003 | The active explicit-confirmation verifier accepts a non-empty confirmation ID without binding it to tenant, actor, action, payload, risk, status, expiry or consumption. | Root WorkCore provider binding and `ExplicitConfirmationVerifier`. | Open. |
| P0-004 | Tenant-owned model reads can remain unscoped when no tenant context is installed. | `BelongsToCompany` global-scope behaviour. | Open. |

## P1 gaps

- MagicAI host adapter contract is incomplete.
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

### P0-001

1. Added `tests/test_workcore_permission_resolver_contract.py` before production changes.
2. Confirmed the test failed because both resolver copies contained `WorkCoreAccessLevel::Manage`.
3. Confirmed the canonical WorkCore permission resolver uses `WorkCoreAccessLevel::All` for privileged membership.
4. Replaced `Manage` with `All` in both resolver copies.
5. Re-ran the isolated regression reproduction and confirmed one test passed with zero failures.

## Next action

Investigate P0-002 by tracing every API middleware source, generated package path and MagicAI authentication contract before changing route configuration.
