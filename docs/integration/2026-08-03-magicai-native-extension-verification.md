# MagicAI-native WorkCore extension verification

Date: 2026-08-03

## Scope

This verification covers the source-controlled parent `WorkCore` vertical-suite wrapper, the five parent-dependent add-ons, the deterministic release builder, the generated extension folders and the Laravel 10 MagicAI host fixture.

## Local verification

- Python repository and extension tests: **54 passed**.
- Generated PHP syntax: **2,137 files passed `php -l`** across native wrappers, fixture code and generated releases.
- Generated extension validator: **6/6 extension folders passed**.
- Ownership source preservation: **2,158/2,158 tracked WorkCore files preserved**.
  - **2,153** files are available through the parent runtime autoloader.
  - The five legacy aggregate group providers are byte-preserved under `WorkCore/docs/source/aggregate-providers/` and intentionally excluded from the runtime autoload path.
- Deterministic ZIP test: repeated builds produce identical SHA-256 values.
- Secret and residue guard: `.env`, nested ZIP, vendor, node_modules, session, cache, log and Livewire temporary content are rejected.

## Supplied WorkCore verification suites

A reconstructed host tree was created from `integration/host-overlay` and the generated parent extension.

| Suite | Result |
|---|---:|
| WorkCore extraction policy | 18/18 passed |
| Finance completion architecture | 500/500 passed |
| Finance completion behaviour | 12/12 passed |
| WorkCore extraction architecture | 1,521/1,522 passed |

The one legacy extraction assertion that no longer passes requires the parent provider to automatically load enabled modules outside aggregate providers. That behaviour conflicts with independent add-on installation: because the parent release contains the complete canonical source, automatic fallback loading would activate all five domain groups without their add-ons.

The native architecture intentionally supersedes that assertion:

1. The parent defines available WorkCore module providers but loads no domain group by itself.
2. Each add-on calls `WorkModuleRegistry::loadMany()` with only its declared module keys.
3. Add-ons remain dormant when the parent or registry is absent.
4. The five former aggregate provider source files remain preserved for provenance but cannot be autoloaded at runtime.
5. Laravel 10 host verification fails when the loaded module set differs from the selected extension profile.

## Laravel 10 / MagicAI host matrix

`.github/workflows/magicai-native-laravel10.yml` creates a real Laravel 10 application and reproduces MagicAI's `class_exists()` provider-map loading. It covers:

- parent only;
- Commercial;
- Business Network + Work Operations;
- Property Operations + Workforce Assurance + Work Operations;
- all five add-ons;
- WorkCore globally disabled;
- Commercial add-on present while the parent is absent.

The parent and full profiles also execute the complete migration sequence using SQLite. GitHub Actions results must pass before the branch is considered installation-verified.
