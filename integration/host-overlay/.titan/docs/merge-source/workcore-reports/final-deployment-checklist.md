# Final Deployment Checklist

```bash
cp .env.example .env
composer install --no-interaction
php artisan key:generate
php artisan workcore:schema-preflight --strict
php artisan migrate --force
php artisan test
npm ci
npm run build
php artisan optimize
php artisan workcore:outbox:publish --limit=100
```

Then verify:

- Queue workers run separate `realtime`, `workcore`, `automation`, `notifications` and `sync` queues where configured.
- The scheduler invokes the outbox publisher at the chosen cadence.
- Pusher/private-channel authorization works across two different companies.
- Replaying one offline operation UUID with the same payload returns the prior result.
- Reusing that UUID with a changed payload is rejected.
- A stale outbox processing record is recovered after the configured timeout.
- Private attachments cannot be fetched without company membership and conversation access.
- Database backups can be restored and migrations can be rerun safely.
