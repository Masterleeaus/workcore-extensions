# MagicAI-native WorkCore extensions

This directory contains the source-controlled MagicAI wrappers for one parent vertical-suite extension and five parent-dependent add-ons. The complete WorkCore runtime remains in the six development packages under `packages/` and is merged into the parent release only by `tools/build_magicai_extensions.py`.

Generated release folders and ZIPs are written to `dist/magicai-extensions/` and are not committed. Add-ons contain no WorkCore migrations, models or domain services; they activate only their declared module keys through the parent-owned `WorkModuleRegistry`.

Build and validate:

```bash
python tools/build_magicai_extensions.py --repo . --output dist/magicai-extensions
python tools/validate_magicai_extensions.py dist/magicai-extensions/WorkCore dist/magicai-extensions/WorkCore*
```

## Runtime isolation

The parent release preserves all 2,158 tracked source files. Five historical aggregate provider files are archived under `WorkCore/docs/source/aggregate-providers/` instead of the runtime autoload path, because loading them from the parent would activate every domain without its add-on. The remaining 2,153 files form the autoloadable parent runtime.

## Laravel 10 host verification

The real-host matrix is defined in `.github/workflows/magicai-native-laravel10.yml`. It creates a Laravel 10 application, installs selected extension folders under `app/Extensions`, reproduces MagicAI provider-map registration, boots the application, verifies exact module loading and runs migrations for the parent and full profiles.
