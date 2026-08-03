# WorkCore Navigation and Workspaces Design

**Status:** Approved for implementation by continuation of the WorkCore CRM replacement pass

## Goal

Make the MagicAI-native WorkCore extension a first-class user workspace that replaces the paid CRM and Sales extension surfaces without patching MagicAI core or introducing a second navigation authority.

## Authority model

- MagicAI owns the application shell, authentication, themes, Marketplace, SaaS plans and the database-backed menu renderer.
- WorkCore owns CRM, operations, workforce, resources/property and commercial records and actions.
- WorkCore contributes structural navigation records to MagicAI but evaluates company-specific visibility through WorkCore tenant and entitlement services.
- Menu visibility is presentation only. Every route remains protected by authenticated tenant context and a WorkCore capability gate.

## Chosen approach

### Extension-owned structural catalogue

The native parent extension will define one immutable workspace catalogue with five ordered roots:

1. CRM
2. Operations
3. Workforce
4. Resources
5. Commercial

Each workspace and section declares:

- stable WorkCore menu key
- label
- Tabler icon
- route name
- URL segment
- display order
- one or more required WorkCore capabilities
- concise description

The catalogue is provider-neutral and contains no authenticated-user decisions.

### MagicAI menu synchronisation

A native `MagicAIMenuSynchronizer` will upsert WorkCore-owned rows into MagicAI's `menus` table when that table exists.

The synchronizer will:

- create missing WorkCore roots and children
- update vendor-controlled fields: route, route slug, label, icon, type, extension ownership and parent relationship
- preserve administrator-controlled `order` and `is_active` for existing rows
- disable retired `workcore_*` keys instead of deleting them
- support schema differences by writing only columns that exist
- invoke `App\Services\Common\MenuService::regenerate()` when the host service exists
- return deterministic created, updated, unchanged and disabled counts

A `workcore:sync-menus` command provides repair and upgrade execution. A guarded compatibility migration invokes the same synchronizer after extension installation when the MagicAI menu table is already present.

### Routes and workspace shell

The extension will register user routes beneath:

```text
/dashboard/user/workcore
```

Named route families:

```text
dashboard.user.workcore.crm.*
dashboard.user.workcore.operations.*
dashboard.user.workcore.workforce.*
dashboard.user.workcore.resources.*
dashboard.user.workcore.commercial.*
```

Each route uses:

```text
web
auth
workcore.tenant
workcore.capability:<required capability>
```

A generic extension-owned controller renders a namespaced Blade workspace shell using MagicAI's `<x-layouts.app>` component. It validates the workspace and section against the catalogue rather than accepting arbitrary view names.

The first shell is intentionally structural. It provides real routes, local workspace navigation, active-state handling, descriptions and machine-readable metadata without pretending unfinished domain screens are complete.

### Company-filtered workspace manifest

The native extension will expose:

```text
GET /api/v1/workcore/workspaces
GET /api/v1/workcore/workspaces/{workspace}
```

The manifest service will:

- require active tenant context
- include only registered and entitled capabilities
- hide empty roots
- preserve catalogue order
- include the current entitlement revision
- include stable route names and URL paths
- avoid caching final visibility in MagicAI's global menu cache

This API becomes the contract for Titan Flow, mobile navigation, generative UI and future field-worker navigation.

## Workspace catalogue

### CRM

- Overview
- Customers
- Contacts
- Leads
- Pipelines
- Opportunities
- Activities
- Reports

Primary capability family: `workcore.crm`.

### Operations

- Overview
- Work Orders
- Schedule
- Dispatch
- Map and Territories
- Recurring Services
- Service Sites
- Forms and Checklists
- Inspections and Compliance
- Reports

Capability families include operations, scheduling, dispatch, recurring, premises, forms, compliance and territories.

### Workforce

- Overview
- Workers and People
- Rosters
- Attendance
- Attendance Verification
- Compliance and Assurance
- Reports

Capability families include workforce, people, rosters, attendance, attendance verification, compliance and assurance.

### Resources

- Overview
- Premises
- Assets
- Fleet
- Inventory
- Suppliers and Supply
- Documents
- Knowledge Base

Capability families include premises, assets, fleet, inventory, supply, documents and knowledge.

### Commercial

- Overview
- Service Catalogue
- Quotes and Estimates
- Invoices
- Payments and Receivables
- Expenses and Payables
- Procurement
- Reconciliation
- Profitability

Capability families include catalogue, finance, sales, budgeting, expense claims, procurement, e-invoicing and trust accounting.

## Security rules

1. No workspace route is authorised by menu presence.
2. Missing tenant context fails closed.
3. Missing or expired entitlement fails closed.
4. A section is omitted from the manifest when its capability is not registered or not allowed.
5. Unknown workspace and section keys return 404 rather than dynamic view resolution.
6. Menu synchronisation never deletes administrator-created custom rows.
7. Menu synchronisation never silently enables an existing administrator-disabled WorkCore row.
8. WorkCore does not write authenticated-user visibility into MagicAI's globally cached structural menu catalogue.

## Compatibility

- The implementation remains extension-owned under `native-extensions/WorkCore`.
- Standalone shared-foundation packages do not acquire MagicAI menu dependencies.
- Parent-only installation registers routes but exposes no unavailable workspace sections.
- Add-on installation controls visibility through capability registration and entitlement projection.
- The extension remains safe when `menus`, `MenuService` or MagicAI view components are absent during non-MagicAI package tests.

## Testing

### Source-contract tests

Verify:

- exactly five ordered roots
- stable unique keys, routes and URL segments
- every section declares capabilities
- provider loads routes/views and registers the command
- synchronizer preserves order and enabled state
- synchronizer disables retired WorkCore keys only
- routes apply tenant and capability middleware
- controller rejects unknown definitions
- view uses the MagicAI layout component and no legacy Bootstrap/jQuery assets

### Real Laravel 10 fixture

The full native profile will:

1. migrate the WorkCore schema
2. create a MagicAI-compatible `menus` table
3. run the menu synchronizer twice
4. prove the second run is idempotent
5. prove administrator order and disabled state are preserved
6. prove retired WorkCore rows are disabled
7. verify every workspace route is registered
8. resolve the company-filtered manifest using the database-backed entitlement projection
9. prove Commercial disappears after entitlement expiry

## Non-goals for this pass

- Full CRM, dispatch, workforce, property or finance screen implementation
- Rebuilding MagicAI's main sidebar renderer
- Patching core `MenuService::data()`
- Introducing Livewire components before screen-specific read models are selected
- Field-worker bottom navigation
- Operations Assistant or AI Agent tool UI
- Legacy paid-CRM data import
