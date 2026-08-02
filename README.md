# WorkCore Extensions

WorkCore has been extracted from the consolidated MagicAI application into **five domain extensions** backed by one mandatory shared foundation. The split preserves the original canonical PHP namespaces, historical data and governed runtime while replacing automatic module fallback-loading with explicit package ownership.

## Packages

| Package | Responsibility | Owned modules |
|---|---|---|
| [`workcore-shared-foundation`](packages/workcore-shared-foundation) | Tenancy, permissions, governed actions, read models, Rewind, outbox, host adapters, configuration and historical migrations | Shared system infrastructure |
| [`workcore-business-network`](packages/workcore-business-network) | Customers, CRM, catalogue, support, knowledge, reviews, territories and intelligence | CRM, Catalogue, Support, Knowledge, KnowledgeBase, Reviews, Territories, Feedback, Wizards |
| [`workcore-commercial`](packages/workcore-commercial) | Finance, payroll, inventory, procurement, vault and trust accounting | Finance, Payroll, Inventory, Supply, TitanVault, TrustAccounting |
| [`workcore-work-operations`](packages/workcore-work-operations) | Jobs, scheduling, dispatch, recurring work, forms, repairs, fleet and QR operations | Operations, Scheduling, Dispatch, RecurringServices, Forms, Repairs, Fleet, QRCode |
| [`workcore-property-operations`](packages/workcore-property-operations) | Premises, assets, documents and vertical operating profiles | Premises, Assets, Documents |
| [`workcore-workforce-assurance`](packages/workcore-workforce-assurance) | Workforce, people, rosters, attendance, compliance, assurance, credentials and NDIS | Workforce, People, AttendanceVerification, Rosters, Attendance, Compliance, Assurance, Credentials, NDIS |

Every domain extension requires `workcore/shared-foundation` at the same package version.

## Non-negotiable architecture rules

- Every source file and internal module has exactly one package owner.
- Canonical `App\Domains\WorkCore` namespaces remain unchanged during the first extraction phase.
- The shared foundation does not automatically load all optional extension providers.
- Historical migrations remain owned by the shared foundation until clean-install baselines are proven.
- Disabling or uninstalling an extension must not delete operational records, attachments, audit history, offline operations or Rewind history.
- Cross-extension writes must use governed actions, contracts or domain events rather than direct foreign-table writes.

The complete file-level ownership and transformation record is in [`ownership-manifest.json`](ownership-manifest.json). Transfer integrity is recorded in [`IMPORT-PROVENANCE.md`](IMPORT-PROVENANCE.md).

## Validate the repository

```bash
python -m unittest tests/test_repository_integrity.py -v
python tools/validate_repository.py --repo .
find packages -type f -name '*.php' -print0 | xargs -0 -n1 -P4 php -l
```

Continuous integration repeats the ownership, manifest, checksum, dependency and PHP syntax checks for every pull request and relevant branch push.

## Development workflow

1. Branch from `main`; never develop directly on `main`.
2. Change only files owned by the target extension or shared foundation.
3. Update `extension.json`, `composer.json`, `files.sha256.json` and `ownership-manifest.json` when ownership or package contents change.
4. Run the full validation commands above.
5. Open a draft pull request and keep it draft until host integration and partial-install tests pass.

Active extraction work is on [`feature/five-domain-extension-split`](../../tree/feature/five-domain-extension-split) in [draft PR #1](../../pull/1).
