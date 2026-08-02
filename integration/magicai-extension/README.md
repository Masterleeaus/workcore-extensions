# MagicAI 11 WorkCore umbrella adapter

MagicAI Marketplace should register one provider:

```php
'workcore' => App\Extensions\WorkCore\System\WorkCoreServiceProvider::class,
```

The adapter delegates to the canonical WorkCore provider after the shared foundation and selected internal domain packages are installed and autoloadable. It deliberately contains no operational authority and performs no migrations or filesystem writes during `register()`.

This is the zero-core-patch integration boundary identified by the MagicAI scans. The five domain groups remain internal WorkCore packages rather than five unrelated MagicAI authorities.
