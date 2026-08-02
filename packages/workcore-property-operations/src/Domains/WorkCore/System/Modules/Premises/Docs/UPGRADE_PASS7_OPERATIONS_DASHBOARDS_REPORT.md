# ManagedPremises Upgrade Pass 7 — Operating Dashboards and Workflows

Version: 1.7.0

## Scope

Pass 7 converts the generic premise record into an operational surface for rooming houses, residential and commercial real estate, storage facilities, and compatible profile aliases.

## Added

- Company-wide premises portfolio KPIs and profile filtering.
- Profile-specific premise dashboards.
- Configurable workflow-template registry.
- Persistent workflow instances with immutable stage snapshots.
- Stage-by-stage progress, pause, resume, completion, cancellation, assignment, due dates, and history.
- Built-in move-in, move-out, turnover, inspection, renewal, and access-exception workflows.
- Operational alerts derived from existing space, occupancy, agreement, access, incident, condition, compliance, and workflow records.
- Safe AI summaries that exclude access secrets and restricted incident details.

## Built-in operating profiles

### Service sites

- Active service plans and upcoming visits
- Access, incident, condition, and compliance status
- Service site onboarding
- Access readiness review
- Service issue handoff to WorkCore

### Rooming houses

- Occupied and vacant rooms
- Departures and agreement expiries
- Incident and compliance alerts
- Resident move-in and move-out
- Room turnover
- Routine room inspection coordination

### Residential real estate

- Occupied tenancies and vacancies
- Lease expiries and departures
- Condition and compliance status
- Tenant move-in and move-out
- Vacancy turnover
- Lease renewal

### Commercial real estate

- Occupied and available tenancies
- Longer commercial critical-date window
- Access authorisations and WorkCore handoffs
- Business move-in and move-out
- Tenancy turnover
- Commercial lease renewal

### Storage facilities

- Occupied, available, and reserved units
- Occupancy rate
- Access-return exceptions
- Storage customer move-in and move-out
- Storage unit turnover
- Access-exception review

## Domain boundaries

- ManagedPremises owns dashboard aggregation and workflow coordination.
- WorkCore owns maintenance jobs, work orders, scheduling, and dispatch.
- Titan Money owns charges, invoices, payments, deposits, bonds, refunds, arrears, and balances.
- Customers or qualified advisers own legal interpretation.
- Workflow completion never implies financial settlement or legal compliance.

## Compatibility

- Module name, alias, namespace, routes, and existing tables remain intact.
- Existing service-site installations receive the generic operating dashboard.
- No existing premise, occupancy, agreement, access, incident, condition, or compliance history is rewritten.
