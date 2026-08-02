# Pass 3 Integration TODO

- Rewrite `tests/Unit/WorkCoreTenantBoundaryTest.php` against the current `System\Actions` and context contracts.
- Decide whether Finance health routes should be API-only, web-only, or registered under separate prefixes.
- Run `composer install` and `composer dump-autoload -o` in the target PHP environment.
- Run `php artisan about`, `php artisan route:list`, and `php artisan migrate:fresh --seed` against a disposable database.
- Run the complete Pest/PHPUnit suite after the legacy test rewrite.
- Verify all configured WorkCore module providers resolve during Laravel container boot.
