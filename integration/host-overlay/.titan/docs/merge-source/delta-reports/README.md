# Meetup + Titan Zero + WorkCore Delta

Apply this archive over the authoritative `source_code(3).zip` base, preserving paths.

## Scope

This delta contains only files that are new, changed, or moved in the final hardened source release, plus the authoritative populated SQL reference database. Unchanged base files are excluded.

## Counts

- Authoritative base files (excluding macOS metadata): 137
- Final release files: 2156
- Unchanged files excluded from delta: 122
- Created files: 2019
- Modified files: 15
- Detected moved files: 0
- Removed original paths without hash-identical replacement: 0
- Added/modified destination files packaged: 2034

## Database

`database/reference/database_with_data_authoritative.sql` is byte-for-byte copied from `database_with_data(2).sql`.
SHA-256: `b39907636f5d30001533a5cd75f31475aae58842ff848a4ec1d70cfd67ab8d0c`

## Deployment status

Source integration and static validation are complete. Production readiness still requires installation of Composer/NPM dependencies, migration execution against a disposable restored database, full Laravel/Pest tests, queue/outbox worker tests, and browser offline-sync testing.

## Applying

1. Back up the target application and database.
2. Extract the contents of this ZIP at the application root.
3. Review `delta-reports/deleted-or-relocated-paths.csv` for paths that must be removed or relocated.
4. Restore a disposable copy of the included SQL database.
5. Install dependencies and run the preflight, migrations, tests, queues, and browser checks before production deployment.
