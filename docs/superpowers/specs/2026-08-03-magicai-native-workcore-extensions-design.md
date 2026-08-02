# MagicAI-Native WorkCore Extensions Design

## Objective

Convert the verified WorkCore five-domain package split into six installable MagicAI 11 extension packages:

1. `WorkCore` — parent vertical-suite extension containing the complete WorkCore runtime, shared migrations, tenant context and host adapters.
2. `WorkCoreBusinessNetwork` — add-on activating Business Network modules.
3. `WorkCoreCommercial` — add-on activating Commercial modules.
4. `WorkCoreWorkOperations` — add-on activating Work Operations modules.
5. `WorkCorePropertyOperations` — add-on activating Property Operations modules.
6. `WorkCoreWorkforceAssurance` — add-on activating Workforce Assurance modules.

## Source authority

The implementation follows `MagicAI-SaaS-Extension-Blueprint-v3` and the uploaded corpus-wide deep-scan report. It retains the exact five-field `extension.json`, adds `extension.manifest.json`, uses `app/Extensions/<Folder>`, implements the verified marketplace interfaces, declares parent/add-on dependencies and keeps uninstall data-safe.

## Runtime architecture

The parent extension ships all WorkCore PHP source under `Runtime/Domains/WorkCore` and registers a narrow prefix autoloader for `App\\Domains\\WorkCore\\`. This avoids rewriting 2,090 PHP files while keeping every installed file inside `app/Extensions/WorkCore`.

The parent provider registers the canonical `App\\Domains\\WorkCore\\WorkCoreServiceProvider`, which defines all module providers but does not automatically load optional modules. Each add-on provider remains dormant when the parent runtime or `WorkModuleRegistry` is absent. When available, it registers exactly one aggregate group provider.

Provider-map order is parent first, followed by the five add-ons.

## Packaging model

Source-controlled files under `native-extensions/` contain only MagicAI-native manifests, wrapper providers, package documentation and add-on configuration. `tools/build_magicai_extensions.py` deterministically composes release folders and ZIPs from the six existing package sources.

The parent ZIP contains:

- native parent wrapper and manifests;
- merged WorkCore runtime from all six development packages;
- source provenance and ownership manifests;
- one migration set, owned by Shared Foundation.

Add-on ZIPs contain only their manifests, provider, configuration and documentation. They do not duplicate WorkCore models, migrations or services.

## Compatibility and lifecycle

- MagicAI: `>=11.0`
- Laravel: `^10.0`
- PHP: `^8.2`
- Tenant key: `company_id`
- Version: `0.1.1`
- Upgrade: forward-only migrations
- Default uninstall policy: retain
- Static uninstall methods are idempotent and never drop WorkCore tables.
- Queue jobs must carry tenant/company and correlation identifiers.

## Validation gates

The conversion must prove:

- six exact extension folders and provider-map keys;
- legacy and sidecar manifest agreement;
- parent/add-on dependency declarations;
- parent-absent add-on safety;
- exact aggregate provider mapping for each add-on;
- no migrations or domain source duplicated into add-ons;
- complete preservation of 2,158 ownership-tracked WorkCore files in the parent runtime;
- deterministic ZIP bytes;
- PHP syntax for every generated PHP file;
- no `.env`, secrets, runtime sessions, temporary uploads, vendor or node_modules files in releases.

## Out of scope for this conversion slice

- modifying MagicAI core marketplace code beyond a provider-map snippet;
- enabling dashboard routes that are not yet protected by the required MagicAI CSRF and tenancy hardening;
- destructive data uninstall;
- cross-extension commercial workflows beyond existing WorkCore aggregate providers.
