# Pass 1 — Hardening + Safe Foundations (ManagedPremises)

Date: 2026-01-01

## Fixed critical breakages
- Replaced truncated/ellipsis `Routes/web.php` with full, valid route definitions (account prefix + route names).
- Rebuilt `ManagedPremisesServiceProvider.php` (removed ellipsis, safe boot early-return, view/lang/config registration).
- Sidebar now uses **safe guards** (no DB calls, checks `user()` and permissions).

## Added permissions + policies
- Permissions manifest: `Support/Permissions.php` + `Config/permissions.php`.
- Added `Policies/PropertyPolicy.php` and `Providers/AuthServiceProvider.php`.

## Added property operations essentials
- Keys & Access register (controller, entity, migration, view).
- Photo gallery (controller, entity, migration, view).
- Checklists (controller, entity, migration, view).
- Module settings (migration + Settings helper + controller + view).

## Titan Zero integration (safe)
- Added `@includeIf('titanzero::partials.ask-titan', ...)` on Property show page with contextual payload + suggestions.

## Added safety scaffolding
- Middleware `EnsureCompanyScope`
- Context builder `Support/PropertyContextBuilder`
- Events + listener for basic lifecycle logging
- Partial views for property tabs and empty states

## Files added/changed
- Added: 41 files
- Changed: `Routes/web.php`, `Providers/ManagedPremisesServiceProvider.php`, `Resources/views/sections/sidebar.blade.php`, `Resources/views/properties/show.blade.php`, `Resources/lang/en/app.php`, `module.json`
