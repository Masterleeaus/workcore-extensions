# MagicAI host compatibility profiles

These profiles define the package combinations that are continuously installed and booted against the extracted Laravel 12 MagicAI host overlay.

| Profile | Packages | Intended use |
|---|---|---|
| `foundation` | Shared Foundation | Runtime, schema, tenancy, governed actions and host contracts without optional domain providers |
| `commercial` | Foundation + Commercial | Finance, payroll, inventory, supply, vault and trust accounting |
| `business-operations` | Foundation + Business Network + Work Operations | CRM, support, catalogue, work orders, scheduling, dispatch and recurring service workflows |
| `property-workforce` | Foundation + Work Operations + Property Operations + Workforce Assurance | Premises, assets, vertical operations, workers, NDIS, attendance, compliance and assurance |
| `full` | All six packages | Complete WorkCore installation |

## Compatibility rules

- All packages currently ship as version `0.1.0` and target PHP 8.2+ with Laravel 11 or 12.
- Shared Foundation is the only Laravel auto-discovered package provider. It detects and registers installed group providers using `class_exists`.
- Missing optional packages are skipped; Shared Foundation does not directly include their files.
- Domain-level cross-package capabilities are declared as Composer `suggest` entries and `extension.json` `integrates_with` metadata.
- Historical migrations remain in Shared Foundation, so adding or removing an optional package does not remove its existing tables.
- Database-specific optional indexes are guarded by driver support. The AI knowledge full-text index is created on MySQL, MariaDB and PostgreSQL, while SQLite retains the table and data without the unsupported index.
- Uninstall hooks and destructive schema automation are prohibited.

The `property-workforce` profile deliberately includes Work Operations because trade compliance, callbacks and worker assignment workflows cross those three domains. Business Network and Work Operations are paired in their supported operational profile because support-ticket conversion targets Work Orders.

## Generate a host Composer file

```bash
python tools/generate_host_profile.py \
  --host-composer integration/host-overlay/composer.json \
  --packages-root packages \
  --profiles compatibility/install-profiles.json \
  --profile full \
  --output integration/host-overlay/composer.json
```

The generated host file uses local Composer path repositories with copied packages rather than symlinks.
