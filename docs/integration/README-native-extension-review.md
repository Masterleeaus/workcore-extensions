# Native extension review entrypoint

Review the native conversion in this order:

1. `docs/superpowers/specs/2026-08-03-magicai-native-workcore-extensions-design.md`
2. `native-extensions/catalogue.json`
3. `native-extensions/WorkCore/extension.manifest.json`
4. The five add-on manifests and service providers
5. `integration/magicai-extension/MarketplaceServiceProvider-workcore-map.php`
6. `tools/build_magicai_extensions.py`
7. `tools/validate_magicai_extensions.py`
8. `integration/magicai-10-fixture/profiles.json`
9. `docs/integration/2026-08-03-native-extension-ci-evidence.md`

The pull request must remain draft until authenticated business-action scenarios are exercised against the complete MagicAI/Titan Zero host.
