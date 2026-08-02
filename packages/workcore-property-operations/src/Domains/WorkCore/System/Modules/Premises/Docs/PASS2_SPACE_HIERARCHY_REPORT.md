# ManagedPremises Pass 2 — Space Hierarchy

Version: 1.2.0

## Delivered

- Added `pm_premise_spaces` as the canonical physical-space table.
- Supports unlimited parent/child hierarchy for buildings, floors, zones, rooms, suites, sheds, lockers, bays and shared areas.
- Added availability, capacity, area, occupiable, bookable, billable and shared-space fields.
- Migrates existing `pm_property_units` and `pm_property_rooms` records without deleting or rewriting legacy data.
- Mirrors all newly created legacy units and rooms into canonical spaces.
- Deletes mirrored canonical records when legacy records are deleted.
- Added company-scoped CRUD routes and a hierarchy management screen.
- Added API and AI capability declarations for space trees and availability.

## Compatibility boundary

`PropertyUnit` and `PropertyRoom` remain supported. They are legacy compatibility interfaces and are not removed in this pass. New vertical functionality should target `PremiseSpace`.

## Deferred to Pass 3

Occupants, reservations, allocations, transfers, move-in and move-out lifecycle are intentionally excluded until the canonical space hierarchy is established.
