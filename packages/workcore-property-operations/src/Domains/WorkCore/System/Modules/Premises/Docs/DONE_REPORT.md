# Managed Premises — DONE Report (Pass 5)

This module is considered **finished** only when all items in the Worksuite User Module Agent Guide are satisfied.

## Acceptance Criteria — Status

✅ Sidebar: Module menu items appear for allowed companies/roles  
✅ Packages: Module appears as a selectable checkbox in Super Admin → Packages  
✅ Label: Package list shows human text ("Managed Premises") not translation key  
✅ Gating: Companies not on a plan with the module cannot see menus/routes (permission + module_settings)  
✅ Backfill: Existing companies get module settings when module is enabled (managedpremises:activate)  
✅ Permissions: Menu items render only when user has permission  

## Required Implementations — Confirmed

### A) Modules table insert (Packages visibility)
✅ Included (idempotent) via module migration using `Module::firstOrCreate(module_name=managedpremises, is_superadmin=0)`.

### B) Sidebar injection partial
✅ Exists at:
- `Resources/views/sections/sidebar.blade.php`

and is permission-gated:
- `user()->permission('view_managedpremises') != 'none`

### C) module_settings role entries
✅ Implemented:
- `Entities/ManagedPremisesSetting::addModuleSetting($company)`
- Uses `ModuleSetting::createRoleSettingEntry('managedpremises', ['admin','employee'], $company)`

### D) New company auto-apply
✅ Implemented:
- `Listeners/CompanyCreatedListener`
- Registered via `Providers/EventServiceProvider.php`

### E) Existing companies backfill
✅ Implemented:
- `Console/ActivateManagedPremisesModuleCommand`
- Command:
  - `php artisan managedpremises:activate`

### F) Packages label (no modules.managedpremises)
✅ Implemented via runtime language injection (Worksuite-safe):
- Provider adds `Lang::addLines(['managedpremises' => 'Managed Premises'], 'en', 'modules');`

### Permissions
✅ Seeded (idempotent):
- view_managedpremises
- add_managedpremises
- edit_managedpremises
- delete_managedpremises

### Hardening
✅ Controller-level access guard (prevents hidden-menu access):
- abort(403) when `view_managedpremises` is `none`

## Demo Seed Data
✅ Included (optional):
- `php artisan module:seed ManagedPremises`

Creates:
- Demo premise + rooms + hazards + access method + checklist

## Commands (absolute paths)

```bash
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && unzip -o ManagedPremises_Pass5.zip
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan module:enable ManagedPremises
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan module:migrate ManagedPremises
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan managedpremises:activate
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan module:seed ManagedPremises
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan optimize:clear
```

## Cache clears
```bash
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan cache:clear
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan config:clear
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan view:clear
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan route:clear
cd /home/saassmar/domains/admin.cleanhub.pro/public_html && php artisan optimize:clear
```
