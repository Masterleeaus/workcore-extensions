# WorkCore Navigation and Workspaces Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a production-ready MagicAI-native WorkCore menu catalogue, route shell, company-filtered workspace manifest and idempotent menu synchronisation.

**Architecture:** WorkCore owns an immutable structural workspace catalogue. MagicAI receives only structural database menu rows; tenant and entitlement visibility is evaluated by WorkCore at route and manifest boundaries. A generic extension-owned Blade shell provides valid user routes without copying legacy WorkSuite views.

**Tech Stack:** PHP 8.2, Laravel 10, Blade, SQLite fixture, Python unittest source contracts, MagicAI native extension lifecycle.

## Global Constraints

- Work only on `upgrade/workcore-crm-replacement-foundation`.
- Do not modify MagicAI core.
- Keep all MagicAI-specific runtime code inside `native-extensions/WorkCore`.
- Preserve WorkCore data and administrator menu order/enabled choices.
- Every web route requires authentication, active WorkCore tenant context and capability middleware.
- Final visibility must not enter MagicAI's global structural menu cache.
- Follow test-driven development and keep PR #4 draft and unmerged.

---

### Task 1: Workspace catalogue contract

**Files:**
- Create: `tests/test_workcore_workspace_navigation.py`
- Create: `native-extensions/WorkCore/System/Navigation/WorkCoreWorkspaceCatalogue.php`
- Modify: `native-extensions/WorkCore/config/workcore-native.php`

**Interfaces:**
- Produces: `WorkCoreWorkspaceCatalogue::all(): array`, `workspace(string): ?array`, `section(string,string): ?array`, `menuDefinitions(): array`.

- [ ] **Step 1: Write the failing test**

Assert exactly five ordered roots (`crm`, `operations`, `workforce`, `resources`, `commercial`), globally unique menu keys and route names, valid capability keys and no empty section lists.

- [ ] **Step 2: Run test to verify it fails**

Run: `python -m unittest tests/test_workcore_workspace_navigation.py -v`
Expected: FAIL because the catalogue does not exist.

- [ ] **Step 3: Implement the catalogue**

Load definitions from `workcore-native.workspaces`, validate the schema in the constructor, expose immutable normalized arrays and flatten them into MagicAI menu definitions.

- [ ] **Step 4: Run test to verify it passes**

Run: `python -m unittest tests/test_workcore_workspace_navigation.py -v`
Expected: PASS for catalogue tests.

- [ ] **Step 5: Commit**

Commit message: `Add WorkCore workspace catalogue`

---

### Task 2: Company-filtered manifest

**Files:**
- Create: `native-extensions/WorkCore/System/Navigation/WorkCoreWorkspaceManifest.php`
- Create: `native-extensions/WorkCore/System/Http/Controllers/WorkspaceManifestController.php`
- Create: `native-extensions/WorkCore/routes/api.php`
- Modify: `native-extensions/WorkCore/System/WorkCoreServiceProvider.php`
- Test: `tests/test_workcore_workspace_navigation.py`

**Interfaces:**
- Consumes: `WorkCoreWorkspaceCatalogue`, `CapabilityRegistry`, `EntitlementResolverContract`, `EntitlementRevisionResolverContract`, `TenantContextContract`.
- Produces: `WorkCoreWorkspaceManifest::forCompany(int): array`, `findForCompany(int,string): ?array`.

- [ ] **Step 1: Add failing manifest tests**

Require active tenant use, registered-capability checks, entitlement checks, empty-root removal, stable order and entitlement revision output.

- [ ] **Step 2: Verify red state**

Run the focused Python test and confirm missing manifest/controller/routes cause failure.

- [ ] **Step 3: Implement minimal manifest and controller**

Filter each section by registered capability and effective entitlement. Include `company_id`, `entitlement_revision`, `workspaces`, route names and paths. Return 404 for an unavailable workspace.

- [ ] **Step 4: Register native API routes**

Use the existing native API middleware stack and route names `api.workcore.workspaces.index` and `api.workcore.workspaces.show`.

- [ ] **Step 5: Verify green state and commit**

Commit message: `Add capability-filtered workspace manifest`

---

### Task 3: MagicAI web route shell

**Files:**
- Create: `native-extensions/WorkCore/System/Http/Controllers/WorkspaceController.php`
- Create: `native-extensions/WorkCore/routes/user.php`
- Create: `native-extensions/WorkCore/resources/views/workspace.blade.php`
- Create: `native-extensions/WorkCore/resources/lang/en/navigation.php`
- Modify: `native-extensions/WorkCore/System/WorkCoreServiceProvider.php`
- Test: `tests/test_workcore_workspace_navigation.py`

**Interfaces:**
- Consumes: `WorkCoreWorkspaceCatalogue` and `WorkCoreWorkspaceManifest`.
- Produces: named `dashboard.user.workcore.<workspace>.<section>` routes and `workcore::workspace` view.

- [ ] **Step 1: Add failing route/view tests**

Require the provider to load views/translations, all route names to be generated from the catalogue, tenant and capability middleware on every route, and the Blade view to use `<x-layouts.app>` without legacy Bootstrap/jQuery imports.

- [ ] **Step 2: Verify red state**

Run the focused test.

- [ ] **Step 3: Implement route registration and controller**

Register `/dashboard/user/workcore/<workspace>/<section?>`. Resolve only catalogue-defined keys. Use section capability middleware. Return 404 for unknown or unavailable definitions.

- [ ] **Step 4: Implement structural Blade shell**

Render workspace title, description, local section navigation and selected-section metadata using MagicAI-native utility classes and semantic HTML.

- [ ] **Step 5: Verify and commit**

Commit message: `Add MagicAI-native WorkCore workspace shell`

---

### Task 4: Idempotent MagicAI menu synchronisation

**Files:**
- Create: `native-extensions/WorkCore/System/Navigation/MagicAIMenuSynchronizer.php`
- Create: `native-extensions/WorkCore/System/Console/Commands/SyncWorkCoreMenusCommand.php`
- Create: `native-extensions/WorkCore/database/migrations/2026_08_04_010000_sync_workcore_magicai_menus.php`
- Modify: `native-extensions/WorkCore/System/WorkCoreServiceProvider.php`
- Test: `tests/test_workcore_workspace_navigation.py`

**Interfaces:**
- Produces: `MagicAIMenuSynchronizer::sync(): array{created:int,updated:int,unchanged:int,disabled:int}`.

- [ ] **Step 1: Add failing synchronizer tests**

Require schema guards, existing `order` and `is_active` preservation, vendor-field updates, parent linking, retired `workcore_*` disabling, no deletion and optional `MenuService::regenerate()`.

- [ ] **Step 2: Verify red state**

Run the focused test.

- [ ] **Step 3: Implement synchronizer**

Use `Schema` and `ConnectionInterface`; write only existing columns; synchronize parents first, then children; disable retired owned keys; regenerate host caches when available.

- [ ] **Step 4: Add command and guarded migration**

Command signature: `workcore:sync-menus`. Migration invokes the synchronizer only when `menus` exists; `down()` disables WorkCore-owned menu rows.

- [ ] **Step 5: Verify and commit**

Commit message: `Synchronize WorkCore menus with MagicAI`

---

### Task 5: Real Laravel database verification

**Files:**
- Create: `tools/verify_workcore_workspace_navigation.php`
- Modify: `.github/workflows/magicai-native-laravel10.yml`
- Modify: `tests/test_workcore_entitlement_database_fixture.py`
- Test: `tests/test_workcore_workspace_navigation.py`

**Interfaces:**
- Uses the real Laravel application, real WorkCore bindings and SQLite.

- [ ] **Step 1: Add a failing fixture contract**

Require the workflow to run `verify_workcore_workspace_navigation.php` for the full profile after migrations and entitlement projection.

- [ ] **Step 2: Verify red state**

Confirm the source contract fails because verifier and workflow step are absent.

- [ ] **Step 3: Implement verifier**

Create a MagicAI-compatible `menus` table, insert one retired WorkCore row and one administrator-customized current row, synchronize twice, verify idempotency and preservation, inspect named routes, set tenant context and verify the manifest.

- [ ] **Step 4: Add expiry proof**

Expire the seeded subscription, refresh entitlements and prove Commercial is absent from the manifest while bootstrap-safe behaviour remains unchanged.

- [ ] **Step 5: Run the seven-profile Laravel matrix**

Expected: all profiles pass; only `full` executes the database workspace verifier.

- [ ] **Step 6: Commit**

Commit message: `Verify WorkCore navigation in real Laravel host`

---

### Task 6: Integrity and release verification

**Files:**
- Modify generated `packages/*/files.sha256.json` only if package-owned files changed.
- Update: PR #4 verification comment.

- [ ] **Step 1: Refresh deterministic package checksums**

Run the repository checksum generator and return CI to check-only mode.

- [ ] **Step 2: Run all repository tests**

Expected: no failures.

- [ ] **Step 3: Build all six native ZIPs**

Expected: deterministic package build, generated-folder validation, PHP lint and release hash verification pass.

- [ ] **Step 4: Run all six GitHub workflows**

Expected: all green on one exact branch head.

- [ ] **Step 5: Record evidence on draft PR #4**

Do not merge or mark ready for review.
