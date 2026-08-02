# ManagedPremises → Core Integrations

This module ships with **safe default adapters** (no-op) so it never breaks if core modules change.

## Adapter contracts

- `TaskAdapterInterface`: create/update a core Task when a Property visit is scheduled
- `HrAdapterInterface`: reflect staff assignment into HR/roster/attendance (optional)

## How core overrides adapters

Bind your implementation in your app container (or a core provider) to replace the Null adapters.

Example (pseudo):
- bind TaskAdapterInterface => WorksuiteTaskAdapter
- bind HrAdapterInterface => WorksuiteHrAdapter
