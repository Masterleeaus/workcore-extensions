# ManagedPremises Generalisation Pass 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a backward-compatible profile layer that supports rooming houses, real estate, storage facilities, and other managed-premises verticals.

**Architecture:** Keep `Property` as the installed-data compatibility aggregate. Add additive profile and JSON override columns, backed by a focused profile registry service used by requests, views, models, and AI context.

**Tech Stack:** PHP 8+, Laravel, Nwidart Modules, Eloquent, Blade, PHPUnit.

## Global Constraints

- Keep module name `ManagedPremises`, alias `managedpremises`, namespace, providers, routes, and current tables unchanged.
- Do not remove or rename existing fields.
- Existing records must resolve to the `service_site` profile.
- Unknown profile keys must degrade to the `custom` profile.

---

### Task 1: Add profile persistence

**Files:**
- Create: `Database/Migrations/2026_07_22_000700_add_profile_layer_to_pm_properties.php`
- Modify: `Entities/Property.php`

- [x] Add nullable-safe additive columns with defaults.
- [x] Add JSON and boolean casts.
- [x] Add profile constants and profile helper methods.
- [x] Verify PHP syntax.

### Task 2: Add profile registry

**Files:**
- Create: `Config/premises_profiles.php`
- Create: `Services/PremisesProfileRegistry.php`
- Modify: `Providers/ManagedPremisesServiceProvider.php`

- [x] Define built-in profiles, terminology, features, and premise types.
- [x] Implement fallback and override merging.
- [x] Register the service as a singleton.
- [x] Verify PHP syntax.

### Task 3: Update create/edit flows

**Files:**
- Modify: `Http/Requests/StorePropertyRequest.php`
- Modify: `Resources/views/properties/ajax/form.blade.php`
- Modify: `Http/Controllers/PropertiesController.php`
- Modify: `Resources/lang/en/app.php`

- [x] Validate profile metadata and JSON-compatible arrays.
- [x] Supply profile/type registries to the form.
- [x] Render profile and expanded premise-type selectors.
- [x] Keep legacy fields accepted.

### Task 4: Expand API and AI context

**Files:**
- Modify: `Services/PropertyProfileService.php`
- Modify: `Support/PropertyContextBuilder.php`
- Modify: `manifests/ai.php`
- Modify: `module.json`

- [x] Return profile metadata and merged features safely.
- [x] Add profile-aware AI capabilities.
- [x] Generalise module metadata and bump version.

### Task 5: Tests and release evidence

**Files:**
- Create: `Tests/Unit/PremisesProfileRegistryTest.php`
- Create: `Tests/Feature/PropertyProfileLayerTest.php`
- Create: `Docs/PASS6_PROFILE_GENERALISATION_REPORT.md`

- [x] Cover built-in profiles, custom fallback, and override merging.
- [x] Cover model casts/default profile contract.
- [x] Run syntax checks and archive the module.
