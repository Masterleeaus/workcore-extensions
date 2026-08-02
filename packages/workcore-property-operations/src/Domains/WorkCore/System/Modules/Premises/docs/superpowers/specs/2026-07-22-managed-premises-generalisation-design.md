# ManagedPremises Generalisation — Pass 1 Design

## Goal

Generalise ManagedPremises from a tradie/jobsite-oriented module into a profile-driven premises platform while preserving its name, namespace, routes, tables, existing fields, and installed data.

## Architecture

The existing `Property` aggregate remains the compatibility root. A new `profile_key` selects a built-in or custom premises profile, while JSON columns store optional terminology, feature overrides, and profile-specific attributes. A registry service supplies default labels, features, and top-level premise types without hard-coding vertical behaviour into controllers or views.

## Pass 1 scope

- Preserve all existing models, endpoints, fields, and permissions.
- Add profile metadata to `pm_properties` through an additive migration.
- Add built-in profiles for service sites, rooming houses, residential rentals, commercial properties, strata properties, storage facilities, warehouses, office centres, community housing, mixed use, and custom premises.
- Replace the three-option top-level type selector with a general type registry.
- Add profile-aware terminology and feature lookup APIs.
- Expand AI context with profile, terminology, features, and attributes.
- Keep cleaning-specific columns available only as legacy compatibility data.
- Do not implement spaces, occupancy, agreements, finance, or compliance records in this pass.

## Compatibility rules

- Existing records default to `service_site`.
- Existing `type`, cleaning, key, access, contact, unit, room, job, visit, and inspection data are not removed or rewritten.
- Unknown/custom profile keys remain valid and fall back to the custom profile definition.
- JSON override values merge over built-in defaults.
- Sensitive access values are not added to AI context.
