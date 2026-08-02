# WorkCore Five-Domain Extension Split Design

## Goal

Divide the consolidated WorkCore implementation into five independently packaged domain groups without losing any source module, action, read model, migration, route, configuration, test, or host-integration asset.

## Architecture

The repository is a monorepo containing one non-counted shared foundation package and five domain extension packages:

1. Business Network
2. Commercial
3. Work Operations
4. Property Operations
5. Workforce Assurance

The shared foundation owns the WorkCore runtime, central historical migrations, cross-domain contracts, registries, tenancy, authorization, action dispatch, read models, outbox, Rewind, host adapters and compatibility configuration. The five groups own all 35 current module directories exactly once.

## Ownership

### Business Network

Modules: CRM, Catalogue, Support, Knowledge, KnowledgeBase, Reviews, Territories, Feedback, Wizards.

System slices: Intelligence, Expansion and AI.

### Commercial

Modules: Finance, Payroll, Inventory, Supply, TitanVault and TrustAccounting.

### Work Operations

Modules: Operations, Scheduling, Dispatch, RecurringServices, Forms, Repairs, Fleet and QRCode.

### Property Operations

Modules: Premises, Assets and Documents.

System slices: System/Verticals and WorkCore/Verticals.

### Workforce Assurance

Modules: Workforce, People, AttendanceVerification, Rosters, Attendance, Compliance, Assurance, Credentials and NDIS.

## Zero-Loss Rules

- Every module directory has exactly one group owner.
- Every file copied into a package is recorded with SHA-256 and source path.
- Historical migrations remain in Shared Foundation for the first extraction phase.
- The shared root provider skips unavailable module providers instead of throwing and registers only installed group providers.
- The root provider no longer performs an automatic fallback that loads every defined module.
- Group providers load only the configuration keys owned by their package.
- Canonical namespaces remain unchanged during phase one.
- The original consolidated archive is retained as a baseline release artifact.
- Disabling or removing a package never runs destructive migrations.

## Deliverables

- Six Composer packages: Shared Foundation plus five groups.
- Updated group providers and manifests.
- Machine-readable ownership and checksum manifests.
- Verification tests proving all 35 modules are assigned and copied once.
- Individual ZIP releases and a complete workspace ZIP.
- A MiniUp multi-page catalogue with package descriptions and downloadable ZIPs.
