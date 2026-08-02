# Managed Premises — Pass 4 Hardening Report

Date: 2026-02-02 20:42:18

## Goals
- Ensure no legacy TitanSites naming remains
- Ensure route/menu visibility is permission-gated
- Ensure controllers cannot be accessed when permission is 'none'
- Ensure menu migrations do not assume non-existent columns in `menus` table

## Findings
- ✅ No TitanSites strings found in module files (scan performed for: TitanSites, titansites, "titan sites", "Titan Sites").

## Changes Made
### Permission hardening
- Added controller concern:
  - `Http/Controllers/Concerns/EnsuresManagedPremisesPermissions.php`
- Injected `$this->ensureCanViewManagedPremises();` at the start of every public controller method.
- Added trait usage in all module controllers.

### Defensive migrations
- Menu migration(s) now early-exit if `menus` table is missing (fork compatibility).

## Files Changed
- Controllers patched: 23
- Menu migrations patched: 1

## Notes
- This pass focuses on safety and consistency. UI polish + demo seed data is Pass 5.
