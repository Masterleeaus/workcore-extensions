# WorkCore Host Package Compatibility Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make the six-package WorkCore split installable in the MagicAI Laravel host, prove supported partial-install combinations, and prevent optional-package removal from causing boot-time failures or destructive schema changes.

**Architecture:** The shared foundation becomes a versioned Laravel-discoverable Composer package. A manifest-driven host-profile generator creates temporary MagicAI Composer configurations for supported package combinations. Static and real Laravel boot checks validate package discovery, configuration loading, provider isolation, migration retention, upgrades and disabled-extension behavior.

**Tech Stack:** PHP 8.2+, Laravel 11/12, Composer path repositories, Python 3 standard library, GitHub Actions.

## Global Constraints

- Preserve canonical `App\\Domains\\WorkCore` namespaces.
- Preserve all 35 module owners and 2,129 ownership-tracked files.
- Historical migrations remain in Shared Foundation.
- No extension may define destructive uninstall scripts or down-migration automation.
- Optional packages must not be required by Shared Foundation at configuration-load time.
- WorkCore must remain disabled-safe when `WORKCORE_ENABLED=false`.

---

### Task 1: Package Metadata and Optional Configuration Safety

- Add regression tests for Shared Foundation-only configuration loading.
- Version all six packages consistently.
- Add Laravel package discovery for the Shared Foundation provider.
- Replace unconditional Commercial config imports with guarded optional imports.

### Task 2: Supported Install Profiles

- Define supported host profiles and their package sets.
- Add a deterministic host Composer profile generator using local path repositories.
- Validate package closure, package names and provider discovery metadata.

### Task 3: Dependency and Disable-Safety Audit

- Add a static package dependency analyzer.
- Fail on direct `require`/`include` of optional package paths from Shared Foundation.
- Verify no Composer uninstall hooks or destructive extension metadata exist.
- Verify group providers skip unavailable module providers.

### Task 4: MagicAI Host Compatibility Matrix

- Add GitHub Actions matrix jobs for foundation, commercial, operations, property-workforce and full profiles.
- Run Composer resolution, Laravel package discovery and application boot.
- Run SQLite migration checks for foundation and full profiles.
- Test upgrading a foundation install to the full package set without deleting migration history.

### Task 5: Evidence and Pull Request Update

- Run Python regression tests and PHP syntax checks.
- Record the supported profile matrix and known optional integrations.
- Update draft PR #1 with host-compatibility evidence while keeping it draft until the matrix is green.
