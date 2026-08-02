# MagicAI → WorkCore Surgical Extraction Manifest

**Date:** 2 August 2026  
**Source:** `clean-main.zip` (MagicAI/Titan Zero host)  
**Target:** `WorkCore-FULL-MERGED-CLEANED-PASS3-DEEP(1).zip`  
**Result:** Consolidated standalone WorkCore archive

## Integrated capabilities

### Universal Documents and Evidence — 16 source files

- Versioned cross-domain documents
- Operational subject links
- Comments and approval decisions
- Evidence registration and sign-off
- SHA-256 canonical payload hashing
- Evidence chain-of-custody events
- Governed document and evidence read models

Database authority added:

- `tz_documents`
- `tz_document_versions`
- `tz_document_links`
- `tz_document_comments`
- `tz_document_approvals`
- `tz_evidence_items`
- `tz_evidence_signoffs`
- `tz_evidence_chain_events`

### Assurance extensions — 19 source files

- Inspection templates and weighted inspection items
- Inspection runs, results and scoring
- Findings and due-date controls
- Risk matrix and risk register
- Assurance summaries and searches
- Assurance-specific corrective-action workflows
- Incident recording and searching through the existing WorkCore safety authority

New database authority:

- `tz_inspection_templates`
- `tz_inspection_template_items`
- `tz_inspections`
- `tz_inspection_results`
- `tz_assurance_findings`
- `tz_risk_register`

Existing canonical authorities reused:

- `tz_corrective_actions` — extended with nullable `finding_id`
- `tz_safety_incidents` — reused for Assurance incident actions and reads

### Trust Accounting — 14 source files

- Trust accounts and matters
- Receipts and receipt allocations
- Multi-person disbursement approvals
- Controlled disbursement release
- Immutable reversal-and-replacement ledger corrections
- Matter ledgers and trust summaries
- Reconciliation tables

Trust Accounting is installed but **disabled by default**:

```env
WORKCORE_TRUST_ACCOUNTING_ENABLED=false
WORKCORE_TRUST_ACCOUNTING_ROUTES_ENABLED=false
```

The donor dependency on `tz_premises_agreements` was removed. Trust matters now use a host-neutral nullable `agreement_public_id` reference.

## Runtime integration

- Added governed `ReadModelExecutor` registration.
- Added optional standalone WorkCore API routes using `auth:sanctum`.
- Added company-aware action and offline-sync rate limiters.
- Added automatic loading for enabled modules not covered by aggregate providers.
- Changed aggregate module loading to skip disabled or absent modules safely.
- Added a WorkCore company bootstrap command.
- Hardened company switching for stateless requests and hosts without an active-company user column.
- Restored Managed Premises configuration merging.
- Corrected case-sensitive AI and Rosters paths.
- Corrected Trade Compliance configuration resolution.
- Added an external customer/lead/supplier candidate lookup utility.

## Default activation

```env
WORKCORE_DOCUMENTS_ENABLED=true
WORKCORE_DOCUMENTS_ROUTES_ENABLED=false
WORKCORE_ASSURANCE_ENABLED=true
WORKCORE_ASSURANCE_ROUTES_ENABLED=false
WORKCORE_TRUST_ACCOUNTING_ENABLED=false
WORKCORE_TRUST_ACCOUNTING_ROUTES_ENABLED=false
WORKCORE_API_ROUTES_ENABLED=false
```

Actions and read models are registered when their modules are enabled. HTTP routes remain disabled until deliberately exposed by the host.

## Deliberately excluded donor systems

The following MagicAI code was not copied as a second database authority:

- Donor `tz_finance_*` finance module
- Donor `tz_payment_*` payment/reconciliation module
- Donor `tz_premises_agreements` accommodation stack
- Donor `tz_incidents` incident table
- Donor replacement `tz_corrective_actions` table
- MagicAI Passport `auth:api` middleware defaults

WorkCore already contains stronger canonical systems for these areas:

- Titan Money (`tm_*`) for finance, invoices, payments, allocation and reconciliation
- Compliance for safety incidents and corrective actions
- Managed Premises (`pm_*`) for agreements, occupancy and accommodation
- Laravel Sanctum for standalone API authentication

## Migration safety

- 487 created tables scanned across root and WorkCore migrations.
- Duplicate `Schema::create` owners after consolidation: **0**.
- Introduced `tz_finance_*` donor tables: **0**.
- Introduced `tz_payment_*` donor tables: **0**.
- New migration foreign-table references checked: **95**.
- Missing referenced table owners: **0**.

## Verification

- Extraction architecture verifier: **1,507 checks passed**.
- Documents/Assurance/Trust policy suite: **18 checks passed**.
- Changed and newly added PHP files linted: **69**, all passed.
- Simple `App\...` imports checked in changed/new files: **178**, none missing.
- Git whitespace/error check: passed.

A full Laravel application boot and Pest suite were not executed because the supplied archive has no `vendor/` directory and Composer is not installed in the execution environment. The included standalone verification scripts require no Composer dependencies:

```bash
php tests/Architecture/verify_magicai_workcore_extraction.php
php tests/Standalone/WorkCoreExtraction/run.php
```

## Environment hardening

The example environment was updated to:

- remove the shared fixed `APP_KEY`
- keep `APP_DEBUG=false`
- use `LOG_LEVEL=warning`
- use a WorkCore-specific example database name
- document all new activation flags

Generate a unique key during installation with:

```bash
php artisan key:generate
```
