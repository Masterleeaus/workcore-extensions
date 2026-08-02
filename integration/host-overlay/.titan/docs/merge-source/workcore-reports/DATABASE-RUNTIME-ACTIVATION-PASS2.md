# Database and Runtime Activation — Pass 2

## Source database

- Populated reference tables: **15**
- Tables: cache, cache_locks, conversations, failed_jobs, job_batches, jobs, message_reads, messages, migrations, participants, password_reset_tokens, personal_access_tokens, sessions, settings, users
- Host migrations: **14**
- WorkCore migrations: **100**

## Activation controls added

- Non-destructive `workcore:schema-preflight` command.
- Runtime registry boot tests.
- Cross-company conversation isolation test.
- Authoritative database snapshot retained byte-for-byte.

## Deployment order

1. Restore the supplied database.
2. Run `php artisan workcore:schema-preflight`.
3. Back up the database.
4. Run `php artisan migrate --pretend` and inspect SQL.
5. Run `php artisan migrate`.
6. Run `php artisan workcore:diagnose --strict`.
7. Run the WorkCore feature tests.
