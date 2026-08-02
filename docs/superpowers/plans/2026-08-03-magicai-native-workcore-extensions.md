# MagicAI-Native WorkCore Extensions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build six deterministic, installable MagicAI 11 extension ZIPs from the verified WorkCore parent-plus-five-add-ons architecture.

**Architecture:** Keep all canonical WorkCore runtime source in the parent `WorkCore` extension and activate each domain group through a lightweight parent-dependent add-on provider. Use a deterministic Python builder, two-tier manifests and release validators instead of duplicating runtime code across add-ons.

**Tech Stack:** Python 3.12+, PHP 8.2+, Laravel 10, MagicAI 11 provider lifecycle, JSON manifests, ZIP archives, `unittest`.

## Global Constraints

- Legacy `extension.json` contains exactly `name`, `type`, `version`, `description`, `support_telegram`.
- Every extension also contains `extension.manifest.json`.
- Parent folder is `WorkCore`; add-ons use their declared StudlyCase folders.
- Provider namespaces start with `App\\Extensions\\<Folder>\\System`.
- WorkCore runtime preserves canonical `App\\Domains\\WorkCore` namespaces through a parent-owned autoloader.
- Tenant key is `company_id`; `team_id` is not a tenant substitute.
- Add-ons boot dormant when the parent is absent.
- Uninstall retains data and is idempotent.
- Release ZIPs exclude secrets, runtime residue, vendor, node_modules and nested ZIPs.

---

### Task 1: Define native extension catalogue and manifest contracts

**Files:**
- Create: `native-extensions/catalogue.json`
- Create: `native-extensions/<Folder>/extension.json`
- Create: `native-extensions/<Folder>/extension.manifest.json`
- Create: `tests/test_magicai_native_extensions.py`

**Interfaces:**
- Produces `load_native_catalogue(path: Path) -> dict[str, ExtensionDefinition]` for the builder and validator.

- [x] Write failing tests for the exact six keys, folder/provider mappings, two-tier manifest agreement and parent dependencies.
- [x] Run `python -m unittest tests/test_magicai_native_extensions.py -v` and confirm missing catalogue/manifests fail.
- [x] Add catalogue and manifests with version `0.1.1`, MagicAI `>=11.0`, Laravel `^10.0`, PHP `^8.2` and retain uninstall policy.
- [x] Run the test and confirm it passes.
- [x] Commit `feat: define MagicAI-native WorkCore extension contracts`.

### Task 2: Implement parent and add-on providers

**Files:**
- Create: `native-extensions/WorkCore/System/WorkCoreServiceProvider.php`
- Create: `native-extensions/WorkCore/System/Runtime/WorkCoreRuntimeAutoloader.php`
- Create: `native-extensions/WorkCore/config/workcore-native.php`
- Create: five add-on `System/*ServiceProvider.php` files
- Create: five add-on config files
- Modify: `tests/test_magicai_native_extensions.py`

**Interfaces:**
- Produces `WorkCoreRuntimeAutoloader::register(string $runtimeRoot): void`.
- Each add-on loads only its declared module keys through `WorkModuleRegistry` and implements `registerKey()` plus idempotent `uninstall()`.

- [x] Write failing provider-contract tests.
- [x] Confirm failures identify absent providers/autoloader.
- [x] Implement the minimal providers and autoloader.
- [x] Run Python contract tests and `php -l` over `native-extensions`.
- [x] Commit `feat: add native parent and domain add-on providers`.

### Task 3: Build deterministic extension release trees

**Files:**
- Create: `tools/build_magicai_extensions.py`
- Create: `tools/validate_magicai_extensions.py`
- Create: `tests/test_magicai_extension_builder.py`
- Create: `native-extensions/README.md`

**Interfaces:**
- Produces `build_magicai_extensions(repo_root: Path, output_root: Path) -> BuildReport`.
- Produces `validate_extension_root(path: Path) -> list[str]`.

- [x] Write failing tests for six output folders, merged parent runtime, zero add-on source duplication, deterministic ZIPs and residue rejection.
- [x] Confirm tests fail because the builder is absent.
- [x] Implement deterministic merge, checksum/provenance manifests, validation and ZIP creation.
- [x] Run builder tests twice and compare SHA-256 values.
- [x] Commit `feat: build deterministic MagicAI extension releases`.

### Task 4: Add MagicAI provider-map and package CI contracts

**Files:**
- Create: `integration/magicai-extension/MarketplaceServiceProvider-workcore-map.php`
- Create: `.github/workflows/magicai-native-extensions.yml`
- Modify: `tests/test_magicai_native_extensions.py`

**Interfaces:**
- Provider-map keys are `workcore`, `workcore-business-network`, `workcore-commercial`, `workcore-work-operations`, `workcore-property-operations`, `workcore-workforce-assurance` in that order.

- [x] Write failing tests for the map order and CI build/validation commands.
- [x] Implement the map snippet and CI workflow.
- [x] Run all Python tests and generated PHP lint.
- [x] Commit `ci: validate MagicAI-native WorkCore extension packages`.

### Task 5: Generate releases and verify complete source preservation

**Files:**
- Generated only: `dist/magicai-extensions/**`
- Modify: `README.md`
- Modify: `MINIUP.md` after publication transport is created.

**Interfaces:**
- Release report records folder ZIP path, byte length, SHA-256, runtime file count and manifest key.

- [x] Run the builder against the final 2,158-file ownership manifest.
- [x] Run all repository tests.
- [x] Run `php -l` against every generated PHP file.
- [x] Run standalone Finance completion and extraction verification suites.
- [x] Confirm no secrets/runtime residue in ZIP inventories.
- [x] Commit source/docs only; do not commit generated ZIP binaries.
