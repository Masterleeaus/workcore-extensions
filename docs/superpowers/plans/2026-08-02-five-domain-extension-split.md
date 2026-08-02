# WorkCore Five-Domain Extension Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a verified monorepo and release bundle that splits WorkCore into five domain extension groups plus a shared foundation with complete source ownership.

**Architecture:** A Python manifest-driven builder copies canonical source paths without changing namespaces, patches the shared root provider and five group providers, emits package manifests/checksums, and creates release ZIPs. Historical migrations and cross-domain runtime code remain in Shared Foundation during this first phase.

**Tech Stack:** Python 3 standard library, PHP 8+/Laravel source packages, Composer metadata, Git, static HTML/CSS/ES modules for the MiniUp catalogue.

## Global Constraints

- Preserve every one of the 35 WorkCore module directories.
- Assign each module to exactly one group.
- Preserve canonical PHP namespaces.
- Keep historical migrations in Shared Foundation.
- Remove automatic fallback loading from the extracted shared provider.
- Do not add destructive uninstall migrations.
- Build only from `/mnt/data/workcore_magicai_consolidated_scan`.

---

### Task 1: Ownership Model and Failing Verification Tests

**Files:**
- Create: `tests/test_build_extensions.py`
- Create: `tools/build_extensions.py`

**Interfaces:**
- Produces: `GROUPS`, `discover_modules(source_root)`, `validate_ownership(source_root)`, and `build(source_root, output_root)`.

- [ ] Write tests asserting 35 discovered modules, one owner per module, no missing modules and no duplicates.
- [ ] Run tests and verify failure because the builder does not yet exist.
- [ ] Implement the ownership constants and validation functions.
- [ ] Run tests and verify they pass.
- [ ] Commit the ownership model.

### Task 2: Shared Foundation and Group Package Extraction

**Files:**
- Modify: `tools/build_extensions.py`
- Test: `tests/test_build_extensions.py`

**Interfaces:**
- Consumes: validated module ownership.
- Produces: `packages/workcore-shared-foundation` and five group package directories.

- [ ] Add failing tests for package paths, copied module paths and exact single ownership.
- [ ] Run tests and verify failure.
- [ ] Implement canonical-path copy logic, Composer metadata, package README files and extension metadata.
- [ ] Run tests and verify pass.
- [ ] Commit package extraction.

### Task 3: Runtime Provider Safety

**Files:**
- Modify: `tools/build_extensions.py`
- Test: `tests/test_build_extensions.py`

**Interfaces:**
- Produces: patched `WorkCoreServiceProvider.php` and five group providers.

- [ ] Add failing tests proving fallback module loading is absent, unavailable providers are skipped and each group provider contains its complete owned runtime key list.
- [ ] Run tests and verify failure.
- [ ] Implement deterministic provider patching.
- [ ] Run PHP syntax checks and Python tests.
- [ ] Commit runtime isolation changes.

### Task 4: Zero-Loss Manifests and Releases

**Files:**
- Modify: `tools/build_extensions.py`
- Test: `tests/test_build_extensions.py`

**Interfaces:**
- Produces: `dist/ownership-manifest.json`, per-package `files.sha256.json`, six package ZIPs and a complete workspace ZIP.

- [ ] Add failing tests for complete module and checksum manifest coverage.
- [ ] Run tests and verify failure.
- [ ] Implement manifests and deterministic ZIP creation.
- [ ] Verify every original WorkCore module file exists in exactly one package.
- [ ] Commit release generation.

### Task 5: MiniUp Catalogue

**Files:**
- Create: `site/index.html`
- Create: `site/packages.html`
- Create: `site/architecture.html`
- Create: `site/css/tokens.css`
- Create: `site/css/base.css`
- Create: `site/css/layout.css`
- Create: `site/css/components.css`
- Create: `site/js/catalogue.js`
- Copy: `site/downloads/*.zip`

**Interfaces:**
- Consumes: release ZIPs and ownership manifest.
- Produces: a responsive, multi-page static catalogue publishable as one MiniUp project ZIP.

- [ ] Create the catalogue from verified release metadata.
- [ ] Validate all links and file paths locally.
- [ ] Publish the project ZIP through MiniUp.
- [ ] Record the exact MiniUp URL in `MINIUP.md`.
- [ ] Commit the catalogue and publication record.

### Task 6: GitHub Publication

**Files:**
- Repository-wide.

- [ ] Create or resolve `Masterleeaus/workcore-extensions`.
- [ ] Push `main` and `feature/five-domain-extension-split`.
- [ ] Open a draft pull request from the feature branch to main.
- [ ] Record the exact repository and pull-request URLs.
