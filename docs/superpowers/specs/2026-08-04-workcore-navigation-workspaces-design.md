# WorkCore Navigation and Workspaces Design

**Status:** Implemented on `upgrade/workcore-crm-replacement-foundation`; final CI evidence pending for the hardened head.

## Goal

Make the MagicAI-native WorkCore extension a first-class user workspace that replaces the paid CRM and Sales extension surfaces without patching MagicAI core or introducing a second navigation authority.

## Authority model

- MagicAI owns the application shell, authentication, themes, Marketplace, SaaS plans and the database-backed menu renderer.
- WorkCore owns CRM, operations, workforce, resources/property and commercial records and actions.
- WorkCore contributes structural navigation records to MagicAI but evaluates company-specific visibility through WorkCore tenant and entitlement services.
- Menu visibility is presentation only. Every route remains protected by authentication, active tenant context and an extension-owned entitlement gate.

## Implemented approach

### Immutable extension-owned catalogue

The parent extension defines one immutable catalogue at:

```text
native-extensions/WorkCore/System/Navigation/workspaces.php
```

It contains five ordered roots:

1. CRM
2. Operations
3. Workforce
4. Resources
5. Commercial

The 42 definitions—five roots and 37 sections—declare stable menu keys, labels, Tabler icons, route names, URL segments, display order, required capabilities and descriptions.

`WorkCoreWorkspaceCatalogue` validates duplicate menu keys, route names and URL paths at construction, normalizes the definitions and produces MagicAI menu rows. The catalogue is deliberately outside `config/`: WorkCore keeps exactly one environment-driven native config file, while vendor navigation structure remains immutable extension source.

### MagicAI menu synchronisation

`MagicAIMenuSynchronizer` upserts WorkCore-owned rows into MagicAI's `menus` table when that table exists.

It:

- creates roots before children
- updates vendor-controlled route, route slug, label, icon, type, extension ownership and parent fields
- preserves administrator-controlled `order` and `is_active` on existing rows
- disables retired `workcore_*` rows instead of deleting them
- writes only columns present in the host schema
- invokes `App\Services\Common\MenuService::regenerate()` when available
- returns deterministic created, updated, unchanged and disabled counts

The `workcore:sync-menus` command provides repair and upgrade execution. A guarded compatibility migration uses the same synchronizer and its rollback writes only schema columns that exist.

### Routes and workspace shell

User routes live beneath:

```text
/dashboard/user/workcore
```

Named families:

```text
dashboard.user.workcore.crm.*
dashboard.user.workcore.operations.*
dashboard.user.workcore.workforce.*
dashboard.user.workcore.resources.*
dashboard.user.workcore.commercial.*
```

Every route uses:

```text
web
auth
workcore.tenant
workcore.workspace-capability:<capability-a>|<capability-b>|...
```

`RequireWorkspaceCapability` fails closed without tenant context and permits a route only when at least one declared capability is both registered and entitled. Section routes use their own capabilities; root routes use the union of the root and all child capabilities.

This any-capability model is required because workspace organisation crosses product boundaries. For example, Commercial entitlements own Inventory and Supply capabilities shown inside Resources, while the core product owns Service Catalogue shown inside Commercial.

A generic controller validates workspace and section keys against the catalogue, rechecks the filtered manifest, and renders the namespaced `workcore::workspace` Blade shell using MagicAI's `<x-layouts.app>` component. Unknown or unavailable definitions return 404; no arbitrary dynamic view resolution is used.

### Company-filtered workspace manifest

The native extension exposes:

```text
GET /api/v1/workcore/workspaces
GET /api/v1/workcore/workspaces/{workspace}
```

The manifest:

- requires active tenant context
- includes only sections whose capabilities are registered and entitled
- exposes a root when at least one child section remains visible
- hides empty roots
- preserves catalogue order
- includes the current entitlement revision
- includes stable route names and URL paths
- avoids putting final company visibility in MagicAI's global structural menu cache

This API is the contract for Titan Flow, mobile navigation, generative UI and future field-worker navigation.

## Workspace catalogue

### CRM

Overview, Customers, Contacts, Leads, Pipelines, Opportunities, Activities and Reports.

### Operations

Overview, Work Orders, Schedule, Dispatch, Map and Territories, Recurring Services, Service Sites, Forms and Checklists, Inspections and Compliance, and Reports.

### Workforce

Overview, Workers and People, Rosters, Attendance, Attendance Verification, Compliance and Assurance, and Reports.

### Resources

Overview, Premises, Assets, Fleet, Inventory, Suppliers and Supply, Documents and Knowledge Base.

### Commercial

Overview, Service Catalogue, Quotes and Estimates, Invoices, Payments and Receivables, Expenses and Payables, Procurement, Reconciliation and Profitability.

## Security rules

1. Menu presence never authorizes a workspace route.
2. Missing tenant context fails closed.
3. Missing, unregistered or expired entitlements fail closed.
4. A section is omitted when none of its capabilities is both registered and entitled.
5. A root is omitted when it has no visible sections.
6. Unknown workspace and section keys return 404.
7. Menu synchronization never deletes administrator-created rows.
8. Menu synchronization never silently enables an administrator-disabled WorkCore row.
9. WorkCore does not write authenticated-user visibility into MagicAI's global structural menu cache.
10. Cross-product roots are authorized by any visible child capability, not a single arbitrary primary capability.

## Compatibility

- All MagicAI-specific code remains under `native-extensions/WorkCore`.
- Standalone shared-foundation packages acquire no MagicAI menu dependency.
- Parent-only installation registers routes safely but exposes no unavailable sections.
- Add-on installation controls visibility through capability registration and entitlement projection.
- The extension remains safe when `menus`, `MenuService`, optional timestamp columns or MagicAI view components are absent during non-MagicAI package tests.

## Verification contract

Source tests verify:

- exactly five ordered roots and 42 unique definitions
- stable unique keys, routes and URL paths
- catalogue validation
- capability-filtered manifest behavior
- any-capability route middleware
- namespaced controller and Blade shell
- schema-tolerant, non-destructive and idempotent menu synchronization
- provider lifecycle registration

The real Laravel 10 full-profile fixture verifies:

1. full WorkCore migrations
2. a MagicAI-compatible `menus` table
3. two synchronizer runs and second-run idempotency
4. administrator order and disabled-state preservation
5. retired-row disabling
6. every workspace and manifest route
7. company-filtered manifest resolution using real database entitlement projection
8. Commercial-only entitlements exposing Inventory and Supply inside Resources
9. CRM remaining hidden without the core entitlement
10. Commercial and Resources disappearing after subscription expiry

## Non-goals for this pass

- Full CRM, dispatch, workforce, property or finance screens
- Rebuilding MagicAI's main sidebar renderer
- Patching core `MenuService::data()`
- Introducing Livewire before screen-specific read models are selected
- Field-worker bottom navigation
- Operations Assistant or AI Agent tool UI
- Legacy paid-CRM data import
