# Pass 6 — Premises Profile Generalisation

## Delivered

ManagedPremises now has an additive, backward-compatible profile layer supporting service sites, rooming houses, residential and commercial real estate, strata, storage facilities, warehouses, office centres, community housing, mixed-use premises, and custom verticals.

### Added

- `profile_key` on every premise, defaulting existing installations to `service_site`.
- JSON terminology, feature, and profile-attribute overrides.
- A built-in profile registry with generic premise types.
- Profile selection in the create/edit premise form.
- Profile-aware API and AI context.
- AI restrictions for access credentials and unverified legal requirements.
- Unit and feature tests for registry fallback, merging, and model contracts.

### Preserved

- Module name, alias, namespace, providers, routes, permissions, and current tables.
- Existing property, unit, room, contact, job, visit, inspection, access, cleaning, and service data.
- Existing `type` values and all legacy fields.

## Deferred to later passes

- Hierarchical `PremiseSpace` records.
- Occupancy and party roles.
- Agreement metadata and Titan Money links.
- Availability, move-in, move-out, access custody, condition, incidents, and compliance rule packs.
