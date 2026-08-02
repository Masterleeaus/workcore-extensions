# MagicAI WorkCore Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate the valuable WorkCore code found only in MagicAI into the standalone WorkCore archive without recreating parallel finance, payment, incident, corrective-action, or premises authorities.

**Architecture:** WorkCore remains the canonical runtime. Documents/Evidence is restored as a first-class module. Assurance contributes inspections, findings, and risk-register capabilities while adapting incidents and corrective actions to WorkCore Compliance tables. Trust Accounting is restored as an optional module with a host-neutral agreement reference. MagicAI-specific finance, payments, and accommodation implementations remain excluded.

**Tech Stack:** PHP 8.2, Laravel 12, WorkCore action/read-model registries, Laravel migrations, standalone architecture verification scripts.

## Global Constraints

- Preserve `tm_*` as the sole finance and payment authority.
- Preserve `tz_safety_incidents` as the sole safety incident authority.
- Preserve the existing `tz_corrective_actions` table as the sole corrective-action authority.
- Preserve canonical Premises/ManagedPremises and do not add the donor accommodation repository.
- Use Sanctum-compatible middleware in standalone WorkCore; never copy MagicAI Passport middleware defaults.
- New specialised modules must be feature-flagged and fail closed.
- No duplicate `Schema::create()` declarations for an existing WorkCore table.

---

### Task 1: Add architecture extraction verifier

**Files:**
- Create: `tests/Architecture/verify_magicai_workcore_extraction.php`

**Produces:** A standalone verifier that fails until Documents, Assurance, Trust Accounting, safe module registration, route wiring, and migration authority constraints are present.

- [ ] Write assertions for required module files and provider registrations.
- [ ] Write assertions rejecting duplicate finance/payment/incident/corrective-action schema authorities.
- [ ] Run verifier and confirm it fails on the baseline archive.

### Task 2: Restore Documents and Evidence

**Files:**
- Create: `app/Domains/WorkCore/System/Modules/Documents/**`
- Create: `app/Domains/WorkCore/Database/Migrations/2026_07_26_020000_create_tz_documents_and_evidence_tables.php`
- Modify: `app/Domains/WorkCore/Config/workcore.php`

**Produces:** `workcore.documents` action/read capabilities backed by document version, approval, evidence, sign-off, and chain-of-custody tables.

- [ ] Copy donor module source.
- [ ] Update subject resolution to canonical WorkCore safety incident records.
- [ ] Register permissions and module configuration.
- [ ] Run syntax and architecture verification.

### Task 3: Consolidate Assurance into Compliance authority

**Files:**
- Create: `app/Domains/WorkCore/System/Modules/Assurance/**`
- Create: `app/Domains/WorkCore/Database/Migrations/2026_07_26_020010_create_tz_assurance_extension_tables.php`
- Modify: `app/Domains/WorkCore/Config/workcore.php`

**Produces:** Inspection templates, inspection execution, findings, and risk register, while incidents write to `tz_safety_incidents` and corrective actions write to the existing `tz_corrective_actions` schema.

- [ ] Copy donor assurance module source.
- [ ] Remove parallel incident/corrective-action table expectations.
- [ ] Adapt repository create/update/search/summary methods to canonical Compliance records.
- [ ] Add only non-conflicting assurance tables and a nullable finding link on existing corrective actions.
- [ ] Run syntax and architecture verification.

### Task 4: Restore optional Trust Accounting

**Files:**
- Create: `app/Domains/WorkCore/System/Modules/TrustAccounting/**`
- Create: `app/Domains/WorkCore/Database/Migrations/2026_07_26_040000_create_tz_trust_accounting_tables.php`
- Modify: `app/Domains/WorkCore/Config/workcore.php`

**Produces:** Optional segregated client-money accounts, matters, receipts, allocations, multi-party approvals, disbursements, ledger reversals, and reconciliation structures.

- [ ] Copy donor trust module source.
- [ ] Replace the unavailable `tz_premises_agreements` foreign key with a host-neutral `agreement_public_id` reference.
- [ ] Default the module to disabled until explicitly enabled.
- [ ] Run syntax and architecture verification.

### Task 5: Repair runtime module and API wiring

**Files:**
- Create: `app/Domains/WorkCore/Routes/api.php`
- Create: `app/Domains/WorkCore/Database/Migrations/2026_07_23_120101_create_workcore_business_flows_table.php`
- Create: `app/Console/Commands/WorkCoreBootstrapCompanyCommand.php`
- Create: `app/Domains/WorkCore/System/ReadModels/ReadModelExecutor.php`
- Create: `app/Domains/WorkCore/System/ReadModels/ReadModelExecutionException.php`
- Modify: `app/Domains/WorkCore/WorkCoreServiceProvider.php`
- Modify: `app/Domains/WorkCore/System/Registry/WorkModuleRegistry.php`
- Modify: provider/path/config files identified by the donor delta.

**Produces:** Feature-flagged API routes, rate limiting, safe disabled-module handling, automatic loading of enabled modules, correct case-sensitive paths, and host-neutral company bootstrap support.

- [ ] Apply safe donor runtime fixes while retaining `auth:sanctum` defaults.
- [ ] Register the read-model executor.
- [ ] Load API routes only when enabled.
- [ ] Run architecture verification and all PHP syntax checks.

### Task 6: Package and report

**Files:**
- Create: `MAGICAI-WORKCORE-EXTRACTION-MANIFEST.md`
- Create output ZIP in `/mnt/data`.

**Produces:** A clean merged archive plus an exact inclusion/exclusion and verification manifest.

- [ ] Record imported, adapted, and deliberately excluded systems.
- [ ] Run duplicate-table, namespace, JSON, and PHP syntax checks.
- [ ] Commit the consolidation.
- [ ] Create the final ZIP and checksum.
