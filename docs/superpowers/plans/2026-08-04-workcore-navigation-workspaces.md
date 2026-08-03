# WorkCore Navigation and Workspaces Implementation Plan

> **Execution status:** Implementation complete. Final six-workflow verification remains the release gate.

**Goal:** Add a production-ready MagicAI-native WorkCore menu catalogue, route shell, company-filtered workspace manifest and idempotent menu synchronisation.

**Architecture:** WorkCore owns immutable structural definitions at `native-extensions/WorkCore/System/Navigation/workspaces.php`. MagicAI receives only structural database menu rows. Tenant and entitlement visibility is evaluated by WorkCore at manifest, middleware and controller boundaries. A generic extension-owned Blade shell provides valid user routes without copying legacy WorkSuite views.

**Tech Stack:** PHP 8.2, Laravel 10, Blade, SQLite fixture, Python unittest source contracts and the MagicAI native extension lifecycle.

## Global constraints

- [x] Work only on `upgrade/workcore-crm-replacement-foundation`.
- [x] Do not modify MagicAI core.
- [x] Keep MagicAI-specific runtime code inside `native-extensions/WorkCore`.
- [x] Preserve WorkCore data and administrator menu order/enabled choices.
- [x] Require authentication, active tenant context and an entitlement gate on every web route.
- [x] Keep final company visibility out of MagicAI's global structural menu cache.
- [x] Keep PR #4 draft and unmerged.

---

## Task 1: Workspace catalogue contract

**Implemented files:**

- `tests/test_workcore_workspace_navigation.py`
- `native-extensions/WorkCore/System/Navigation/workspaces.php`
- `native-extensions/WorkCore/System/Navigation/WorkCoreWorkspaceCatalogue.php`

- [x] Write a failing catalogue contract.
- [x] Define five ordered roots and 42 total definitions.
- [x] Validate globally unique menu keys, route names and URL paths.
- [x] Validate non-empty capability and section definitions.
- [x] Keep immutable definitions outside the environment `config/` directory.
- [x] Flatten definitions into MagicAI-compatible menu records.

## Task 2: Company-filtered manifest

**Implemented files:**

- `native-extensions/WorkCore/System/Navigation/WorkCoreWorkspaceManifest.php`
- `native-extensions/WorkCore/System/Http/Controllers/WorkspaceManifestController.php`
- `native-extensions/WorkCore/routes/api.php`

- [x] Require active tenant context.
- [x] Check both capability registration and company entitlement.
- [x] Filter individual sections.
- [x] Expose a root when any child section is visible.
- [x] Hide empty roots.
- [x] Include company ID, entitlement revision, routes and paths.
- [x] Return 404 for unavailable workspaces.

## Task 3: MagicAI web route shell

**Implemented files:**

- `native-extensions/WorkCore/System/Http/Controllers/WorkspaceController.php`
- `native-extensions/WorkCore/System/Http/Middleware/RequireWorkspaceCapability.php`
- `native-extensions/WorkCore/routes/user.php`
- `native-extensions/WorkCore/resources/views/workspace.blade.php`
- `native-extensions/WorkCore/resources/lang/en/navigation.php`

- [x] Generate literal named routes from the catalogue.
- [x] Apply `web`, `auth` and `workcore.tenant` middleware.
- [x] Add `workcore.workspace-capability` as an any-capability entitlement gate.
- [x] Gate root routes by the union of root and child capabilities.
- [x] Gate section routes by each section's declared capabilities.
- [x] Recheck filtered manifest visibility in the controller.
- [x] Reject unknown and unavailable definitions with 404.
- [x] Render a namespaced MagicAI `<x-layouts.app>` Blade shell.
- [x] Avoid legacy Bootstrap, jQuery, Select2 and DataTables assets.

## Task 4: Idempotent MagicAI menu synchronisation

**Implemented files:**

- `native-extensions/WorkCore/System/Navigation/MagicAIMenuSynchronizer.php`
- `native-extensions/WorkCore/System/Console/Commands/SyncWorkCoreMenusCommand.php`
- `native-extensions/WorkCore/database/migrations/2026_08_04_010000_sync_workcore_magicai_menus.php`

- [x] Guard for absent `menus` table and optional columns.
- [x] Synchronize roots before children.
- [x] Repair vendor-controlled fields.
- [x] Preserve existing administrator `order` and `is_active` values.
- [x] Disable retired WorkCore-owned keys without deletion.
- [x] Regenerate MagicAI menu caches when its service exists.
- [x] Return created, updated, unchanged and disabled counts.
- [x] Add `workcore:sync-menus` repair command.
- [x] Use the same synchronizer from the compatibility migration.
- [x] Make rollback tolerant of a missing `updated_at` column.

## Task 5: Native provider lifecycle

**Implemented file:**

- `native-extensions/WorkCore/System/WorkCoreServiceProvider.php`

- [x] Bind catalogue, manifest and synchronizer.
- [x] Load migrations, routes, views and translations.
- [x] Register entitlement-refresh and menu-sync commands.
- [x] Preserve exactly one environment-driven native config file.
- [x] Keep workspace definitions vendor-owned and non-publishable.

## Task 6: Real Laravel database verification

**Implemented files:**

- `tools/verify_workcore_workspace_navigation.php`
- `.github/workflows/magicai-native-laravel10.yml`

- [x] Create a MagicAI-compatible `menus` table.
- [x] Seed a retired WorkCore row.
- [x] Seed an administrator-customized, disabled current row.
- [x] Run menu synchronization twice.
- [x] Prove second-run idempotency.
- [x] Prove administrator order and disabled state are preserved.
- [x] Prove retired WorkCore rows are disabled.
- [x] Verify five workspace route families and two manifest routes.
- [x] Resolve the manifest through real database-backed entitlements.
- [x] Prove a Commercial-only plan exposes Inventory and Supply inside Resources.
- [x] Prove CRM remains hidden without the core entitlement.
- [x] Prove Commercial and Resources disappear after subscription expiry.
- [x] Run the verifier only for the full Laravel fixture profile.

## Task 7: Integrity and release verification

- [x] Keep package checksum validation green; navigation changes are native-parent files rather than split package source.
- [ ] Run all repository tests on the final hardened head.
- [ ] Build and validate all six native ZIPs on that exact head.
- [ ] Lint generated PHP and verify release hashes.
- [ ] Run all seven Laravel host profiles.
- [ ] Confirm all six GitHub workflows are green on one exact SHA.
- [ ] Record exact evidence on draft PR #4.

## Release boundary

Do not merge or mark PR #4 ready for review during this pass. The next product pass begins only after one exact branch head passes every workflow and the result is recorded on the draft PR.
