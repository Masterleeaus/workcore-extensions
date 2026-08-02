# Parties and Occupancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add company-scoped premise parties, occupancy/reservation history, capacity-aware assignment, lifecycle transitions, and transfers without replacing legacy contacts.

**Architecture:** Introduce generic party-role and occupancy tables linked to the existing Property and PremiseSpace records. Keep occupancy state rules in framework-independent domain classes, place transactional lifecycle changes in an application service, and expose the feature through additive routes, controllers, views, permissions, manifests, and profile-aware links.

**Tech Stack:** PHP 8.2+, Laravel/Eloquent, Nwidart Laravel Modules, Blade, MySQL-compatible migrations.

## Global Constraints

- Keep module name `ManagedPremises`, alias `managedpremises`, and namespace `Modules\\ManagedPremises` unchanged.
- Preserve all existing routes, models, tables, and legacy contact behaviour.
- Every query and write must be scoped by `company_id` and `premise_id`.
- Occupancy history is immutable after activation except through explicit lifecycle transitions.
- Financial records remain references only; billing stays outside this module.
- Generic party references must support CRM-backed parties and snapshot-only records.

---

### Task 1: Occupancy state and capacity contracts

**Files:**
- Create: `Domain/Occupancy/OccupancyState.php`
- Create: `Domain/Occupancy/OccupancyCapacityPolicy.php`
- Create: `Tests/contract_pass3.php`

**Interfaces:**
- Produces: `OccupancyState::consumesCapacity(string): bool`, `OccupancyState::canTransition(string,string): bool`, and `OccupancyCapacityPolicy::canAllocate(?int,int,int): bool`.

- [ ] Write standalone failing contract tests for statuses, transitions, and capacity.
- [ ] Run `php Tests/contract_pass3.php` and verify failure because classes do not exist.
- [ ] Implement the minimal domain classes.
- [ ] Run the contract test and verify all assertions pass.

### Task 2: Persistence models and migration

**Files:**
- Create: `Database/Migrations/2026_07_22_000720_create_pm_premise_parties_and_occupancies.php`
- Create: `Entities/PremisePartyRole.php`
- Create: `Entities/PremiseOccupancy.php`
- Modify: `Entities/Property.php`
- Modify: `Entities/PremiseSpace.php`

**Interfaces:**
- Produces company-scoped `partyRoles`, `occupancies`, `currentOccupancies`, and occupancy history relationships.

- [ ] Add party-role and occupancy tables with indexes, foreign keys, snapshots, generic party references, lifecycle dates, source references, transfer lineage, and JSON metadata.
- [ ] Migrate existing property contacts into premise party roles without deleting or changing contacts.
- [ ] Add casts, constants, scopes, and relationships.

### Task 3: Occupancy lifecycle service

**Files:**
- Create: `Services/PremiseOccupancyService.php`

**Interfaces:**
- Produces: `create`, `transition`, and `transfer` transactional operations.

- [ ] Validate company/premise/space consistency and occupiable spaces.
- [ ] Enforce capacity against capacity-consuming occupancy states.
- [ ] Synchronise space availability after every lifecycle change.
- [ ] Preserve transfer history by closing the source and creating a linked destination record.

### Task 4: HTTP surfaces and views

**Files:**
- Create: `Http/Controllers/PremisePartyRolesController.php`
- Create: `Http/Controllers/PremiseOccupanciesController.php`
- Create: `Resources/views/properties/parties.blade.php`
- Create: `Resources/views/properties/occupancies.blade.php`
- Modify: `Routes/web.php`
- Modify: `Resources/views/properties/show.blade.php`
- Modify: `Resources/views/properties/spaces.blade.php`

**Interfaces:**
- Produces premise-level party and occupancy boards, create forms, lifecycle actions, and transfer actions.

- [ ] Add permission-protected premise routes.
- [ ] Validate scoped party and space references.
- [ ] Add profile-aware links and occupancy counts.
- [ ] Keep status history visible rather than deleting records.

### Task 5: Permissions, context, and integration manifests

**Files:**
- Modify: `Config/permissions.php`
- Modify: `manifests/permissions.php`
- Modify: `manifests/api.php`
- Modify: `manifests/ai.php`
- Modify: `manifests/signals.php`
- Modify: `Support/PropertyContextBuilder.php`
- Modify: `Services/PropertyProfileService.php`
- Modify: `module.json`

**Interfaces:**
- Produces role permissions, AI/API capability declarations, occupancy-safe context, and release version `1.3.0`.

- [ ] Add party and occupancy permissions.
- [ ] Add safe list/summarise capabilities and explicit privacy restrictions.
- [ ] Include current occupancy and space capacity summaries without leaking sensitive party data.
- [ ] Declare occupancy lifecycle signals.

### Task 6: Verification and package

**Files:**
- Create: `Docs/PASS3_PARTIES_OCCUPANCY_REPORT.md`

- [ ] Run standalone contract tests.
- [ ] Run PHP syntax checks for every PHP file.
- [ ] Validate all JSON manifests.
- [ ] Run structural assertions for routes, migration columns, permissions, relationships, and version.
- [ ] Build ZIP and run archive integrity test.
