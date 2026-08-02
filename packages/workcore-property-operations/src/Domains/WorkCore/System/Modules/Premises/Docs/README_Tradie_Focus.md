# ManagedPremises — Tradie / Agent / Cleaner Focus

This module stores **Property (Jobsite)** profiles so field teams can work from a single, reliable snapshot.

## What it is
- Property details, access, hazards, keys, photos, checklists, and lightweight job logs.

## What it is NOT
- Not a replacement for core **Jobs / Work Orders / Quotes / Invoices**.
- Links to those modules via `linked_module` + `linked_id` fields on pm_property_jobs.

## Titan Zero
Uses `@includeIf('titanzero::partials.ask-titan', ...)` to request structured outputs without direct model/provider calls.

## Worksuite Sidebar (Menu System)
Managed Premises can appear in the Worksuite sidebar in **two ways**:

1) **DB menus rows (automatic)**
   - This module ships a migration that inserts the required `menus` rows (group + items).
   - Run: `php artisan module:migrate ManagedPremises`.

2) **MenuService + MenuHelper (core app, if your build requires it)**
   Some Worksuite builds require a static config entry (MenuService) and plan/feature gating (MenuHelper).
   If Managed Premises still doesn't show after migration, add these entries in your core app:

**MenuService::data() snippet**
- Key: `managedpremises`
- Children keys: `managedpremises.dashboard`, `managedpremises.sites`, `managedpremises.settings`

Suggested config (adjust icons to your set):

```php
// MenuService::data()
'managedpremises' => [
  'label' => 'Managed Premises',
  'icon' => 'fa fa-map-marker-alt',
  'route' => null,
  'type'  => 'group',
  'extension' => true,
],
'managedpremises.dashboard' => [
  'label' => 'Dashboard',
  'icon' => 'fa fa-chart-line',
  'route' => 'managedpremises.dashboard',
  'type'  => 'item',
  'extension' => true,
],
'managedpremises.sites' => [
  'label' => 'Sites',
  'icon' => 'fa fa-building',
  'route' => 'managedpremises.properties.index',
  'type'  => 'item',
  'extension' => true,
],
'managedpremises.settings' => [
  'label' => 'Settings',
  'icon' => 'fa fa-cog',
  'route' => 'managedpremises.settings.index',
  'type'  => 'item',
  'extension' => true,
],
```

**MenuHelper::checker() snippet**

```php
case 'managedpremises':
case 'managedpremises.dashboard':
case 'managedpremises.sites':
case 'managedpremises.settings':
  return true; // MVP: allow if installed; optionally gate by plan/feature slug later
```
