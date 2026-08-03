from __future__ import annotations

import json
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
NATIVE_ROOT = REPO_ROOT / "native-extensions"

EXPECTED = {
    "workcore": {
        "folder": "WorkCore",
        "family": "vertical-suite",
        "provider": "App\\Extensions\\WorkCore\\System\\WorkCoreServiceProvider",
        "parent": None,
    },
    "workcore-business-network": {
        "folder": "WorkCoreBusinessNetwork",
        "family": "addon",
        "provider": "App\\Extensions\\WorkCoreBusinessNetwork\\System\\WorkCoreBusinessNetworkServiceProvider",
        "parent": {"key": "workcore", "version": "^0.1"},
    },
    "workcore-commercial": {
        "folder": "WorkCoreCommercial",
        "family": "addon",
        "provider": "App\\Extensions\\WorkCoreCommercial\\System\\WorkCoreCommercialServiceProvider",
        "parent": {"key": "workcore", "version": "^0.1"},
    },
    "workcore-work-operations": {
        "folder": "WorkCoreWorkOperations",
        "family": "addon",
        "provider": "App\\Extensions\\WorkCoreWorkOperations\\System\\WorkCoreWorkOperationsServiceProvider",
        "parent": {"key": "workcore", "version": "^0.1"},
    },
    "workcore-property-operations": {
        "folder": "WorkCorePropertyOperations",
        "family": "addon",
        "provider": "App\\Extensions\\WorkCorePropertyOperations\\System\\WorkCorePropertyOperationsServiceProvider",
        "parent": {"key": "workcore", "version": "^0.1"},
    },
    "workcore-workforce-assurance": {
        "folder": "WorkCoreWorkforceAssurance",
        "family": "addon",
        "provider": "App\\Extensions\\WorkCoreWorkforceAssurance\\System\\WorkCoreWorkforceAssuranceServiceProvider",
        "parent": {"key": "workcore", "version": "^0.1"},
    },
}


class NativeExtensionManifestTests(unittest.TestCase):
    def test_catalogue_defines_exactly_parent_plus_five_addons(self) -> None:
        catalogue = json.loads((NATIVE_ROOT / "catalogue.json").read_text(encoding="utf-8"))
        self.assertEqual(set(EXPECTED), set(catalogue["extensions"]))
        self.assertEqual("0.1.1", catalogue["release_version"])
        self.assertEqual("company_id", catalogue["tenant_key"])

    def test_legacy_and_sidecar_manifests_are_host_compatible(self) -> None:
        for key, expected in EXPECTED.items():
            root = NATIVE_ROOT / expected["folder"]
            legacy = json.loads((root / "extension.json").read_text(encoding="utf-8"))
            sidecar = json.loads((root / "extension.manifest.json").read_text(encoding="utf-8"))

            self.assertEqual(
                {"name", "type", "version", "description", "support_telegram"},
                set(legacy),
            )
            self.assertEqual("extension", legacy["type"])
            self.assertEqual("0.1.1", legacy["version"])
            self.assertEqual(legacy["name"], sidecar["name"])
            self.assertEqual(legacy["version"], sidecar["version"])
            self.assertEqual(key, sidecar["key"])
            self.assertEqual(expected["folder"], sidecar["folder"])
            self.assertEqual(expected["family"], sidecar["family"])
            self.assertEqual(expected["provider"], sidecar["provider"])
            self.assertEqual(expected["parent"], sidecar["dependencies"].get("parent"))
            self.assertEqual(">=11.0", sidecar["compatibility"]["magicai"])
            self.assertEqual("^10.0", sidecar["compatibility"]["laravel"])
            self.assertEqual("^8.2", sidecar["compatibility"]["php"])
            self.assertEqual("company_id", sidecar["data"]["tenant_key"])
            self.assertEqual("retain", sidecar["data"]["default_uninstall_policy"])
            self.assertTrue(sidecar["lifecycle"]["idempotent"])
            self.assertEqual("forward-only migrations", sidecar["lifecycle"]["upgrade_strategy"])

    def test_addons_do_not_claim_migration_or_table_ownership(self) -> None:
        for key, expected in EXPECTED.items():
            sidecar = json.loads(
                (NATIVE_ROOT / expected["folder"] / "extension.manifest.json").read_text(encoding="utf-8")
            )
            database = sidecar["database"]
            if key == "workcore":
                self.assertTrue(database["migrations"])
                self.assertGreater(len(database["owned_tables"]), 0)
            else:
                self.assertFalse(database["migrations"])
                self.assertEqual([], database["owned_tables"])
                self.assertEqual([], database["shared_tables"])


class NativeExtensionProviderTests(unittest.TestCase):
    ADDON_MODULES = {
        "WorkCoreBusinessNetwork": [
            "crm", "catalogue", "support", "knowledge", "reviews",
            "territories", "intelligence", "expansion", "wizards", "ai",
        ],
        "WorkCoreCommercial": [
            "finance", "payroll", "inventory", "supply", "vault", "trust_accounting",
        ],
        "WorkCoreWorkOperations": [
            "operations", "scheduling", "dispatch", "recurring", "forms", "repairs", "fleet",
        ],
        "WorkCorePropertyOperations": ["premises", "assets", "documents", "vertical_operations"],
        "WorkCoreWorkforceAssurance": [
            "workforce", "people", "attendance_verification", "rosters",
            "attendance", "compliance", "assurance",
        ],
    }

    def test_parent_provider_registers_runtime_autoloader_and_canonical_kernel(self) -> None:
        provider = (NATIVE_ROOT / "WorkCore/System/WorkCoreServiceProvider.php").read_text(encoding="utf-8")
        autoloader = (NATIVE_ROOT / "WorkCore/System/Runtime/WorkCoreRuntimeAutoloader.php").read_text(encoding="utf-8")

        self.assertIn("ExtensionRegisterKeyProviderInterface", provider)
        self.assertIn("UninstallExtensionServiceProviderInterface", provider)
        self.assertIn("WorkCoreRuntimeAutoloader::register", provider)
        self.assertIn("App\\Domains\\WorkCore\\WorkCoreServiceProvider::class", provider)
        self.assertIn("return 'workcore';", provider)
        self.assertIn("private const PREFIX = 'App\\\\Domains\\\\WorkCore\\\\';", autoloader)
        self.assertIn("spl_autoload_register", autoloader)
        self.assertIn("RuntimeException", autoloader)

    def test_addons_are_parent_guarded_and_load_only_their_owned_module_keys(self) -> None:
        aggregate_names = {
            "BusinessNetworkServiceProvider",
            "CommercialServiceProvider",
            "WorkOperationsServiceProvider",
            "PropertyOperationsServiceProvider",
            "WorkforceAssuranceServiceProvider",
        }
        for folder, modules in self.ADDON_MODULES.items():
            provider_path = NATIVE_ROOT / folder / "System" / f"{folder}ServiceProvider.php"
            provider = provider_path.read_text(encoding="utf-8")
            key = json.loads((NATIVE_ROOT / folder / "extension.manifest.json").read_text())["key"]

            self.assertIn("ExtensionRegisterKeyProviderInterface", provider)
            self.assertIn("UninstallExtensionServiceProviderInterface", provider)
            self.assertIn("WorkModuleRegistry::class", provider)
            self.assertIn("WorkCoreServiceProvider::class", provider)
            self.assertIn("$this->app->bound(WorkModuleRegistry::class)", provider)
            self.assertIn("$registry->loadMany(self::MODULES);", provider)
            self.assertIn(f"return '{key}';", provider)
            for module in modules:
                self.assertIn(f"'{module}'", provider)
            for aggregate in aggregate_names:
                self.assertNotIn(f"App\\Domains\\WorkCore\\Providers\\{aggregate}", provider)
            self.assertNotIn("$this->app->register", provider)
            self.assertNotIn("loadMigrationsFrom", provider)
            self.assertNotIn("Schema::", provider)

    def test_every_native_extension_has_explicit_retain_data_config(self) -> None:
        for expected in EXPECTED.values():
            config_files = list((NATIVE_ROOT / expected["folder"] / "config").glob("*.php"))
            self.assertEqual(1, len(config_files), expected["folder"])
            config = config_files[0].read_text(encoding="utf-8")
            self.assertIn("'enabled' => env(", config)
            self.assertIn("_ENABLED', true)", config)
            self.assertIn("'uninstall_policy' => 'retain'", config)


class MagicAIHostIntegrationContractTests(unittest.TestCase):
    EXPECTED_KEYS = [
        "workcore",
        "workcore-business-network",
        "workcore-commercial",
        "workcore-work-operations",
        "workcore-property-operations",
        "workcore-workforce-assurance",
    ]

    def test_provider_map_lists_parent_before_addons(self) -> None:
        path = REPO_ROOT / "integration/magicai-extension/MarketplaceServiceProvider-workcore-map.php"
        content = path.read_text(encoding="utf-8")
        positions = [content.index(f"'{key}'") for key in self.EXPECTED_KEYS]
        self.assertEqual(sorted(positions), positions)
        for key, expected in EXPECTED.items():
            self.assertIn(f"'{key}'", content)
            self.assertIn(expected["provider"] + "::class", content)

    def test_native_extension_ci_builds_validates_and_lints_generated_packages(self) -> None:
        workflow = (REPO_ROOT / ".github/workflows/magicai-native-extensions.yml").read_text(encoding="utf-8")
        required = [
            "python -m unittest discover -s tests -p 'test_*.py' -v",
            "python tools/build_magicai_extensions.py",
            "python tools/validate_magicai_extensions.py",
            "find dist/magicai-extensions -type f -name '*.php'",
            "php -l",
            "release-report.json",
        ]
        for token in required:
            self.assertIn(token, workflow)


class WorkCoreHostAdapterTests(unittest.TestCase):
    LEGACY_ALIASES = {
        "App\\Console\\Commands\\WorkCoreBootstrapCompanyCommand": "App\\Extensions\\WorkCore\\System\\Console\\Commands\\WorkCoreBootstrapCompanyCommand",
        "App\\Http\\Controllers\\Api\\V1\\WorkCore\\ActionController": "App\\Extensions\\WorkCore\\System\\Http\\Controllers\\ActionController",
        "App\\Http\\Controllers\\Api\\V1\\WorkCore\\BusinessFlowController": "App\\Extensions\\WorkCore\\System\\Http\\Controllers\\BusinessFlowController",
        "App\\Services\\WorkCore\\CustomerPropertyWorkOrderFlow": "App\\Extensions\\WorkCore\\System\\Services\\CustomerPropertyWorkOrderFlow",
        "App\\Support\\WorkCore\\WorkCoreTenantResolver": "App\\Extensions\\WorkCore\\System\\Resolvers\\WorkCoreTenantResolver",
        "App\\Support\\WorkCore\\WorkCorePermissionResolver": "App\\Extensions\\WorkCore\\System\\Resolvers\\WorkCorePermissionResolver",
    }

    def test_parent_registers_extension_owned_host_aliases_before_kernel(self) -> None:
        provider = (NATIVE_ROOT / "WorkCore/System/WorkCoreServiceProvider.php").read_text(encoding="utf-8")
        self.assertIn("WorkCoreHostAliasRegistrar::register", provider)
        self.assertLess(provider.index("WorkCoreHostAliasRegistrar::register"), provider.index("WorkCoreServiceProvider::class"))

        registrar = (NATIVE_ROOT / "WorkCore/System/Runtime/WorkCoreHostAliasRegistrar.php").read_text(encoding="utf-8")
        self.assertIn("class_alias", registrar)
        for legacy, replacement in self.LEGACY_ALIASES.items():
            self.assertIn(legacy, registrar)
            self.assertIn(replacement, registrar)

    def test_host_adapter_classes_are_extension_owned(self) -> None:
        expected_paths = [
            "System/Console/Commands/WorkCoreBootstrapCompanyCommand.php",
            "System/Http/Controllers/ActionController.php",
            "System/Http/Controllers/BusinessFlowController.php",
            "System/Services/CustomerPropertyWorkOrderFlow.php",
            "System/Resolvers/WorkCoreTenantResolver.php",
            "System/Resolvers/WorkCorePermissionResolver.php",
        ]
        for relative in expected_paths:
            content = (NATIVE_ROOT / "WorkCore" / relative).read_text(encoding="utf-8")
            self.assertIn("namespace App\\Extensions\\WorkCore\\System", content)
            self.assertNotIn("namespace App\\Http", content)
            self.assertNotIn("namespace App\\Support", content)
            self.assertNotIn("namespace App\\Services", content)

        action = (NATIVE_ROOT / "WorkCore/System/Http/Controllers/ActionController.php").read_text(encoding="utf-8")
        flow = (NATIVE_ROOT / "WorkCore/System/Http/Controllers/BusinessFlowController.php").read_text(encoding="utf-8")
        tenant = (NATIVE_ROOT / "WorkCore/System/Resolvers/WorkCoreTenantResolver.php").read_text(encoding="utf-8")
        self.assertIn("getAttribute($activeCompanyColumn)", action)
        self.assertIn("getAttribute($activeCompanyColumn)", flow)
        self.assertIn("hasSession", tenant)

    def test_parent_owns_only_compatibility_migrations_outside_runtime(self) -> None:
        migrations = sorted((NATIVE_ROOT / "WorkCore/database/migrations").glob("*.php"))
        self.assertGreaterEqual(len(migrations), 1)
        contents = {path.name: path.read_text(encoding="utf-8") for path in migrations}
        combined = "\n".join(contents.values())

        self.assertIn("active_company_id", combined)
        self.assertIn("workcore_business_flows", combined)
        self.assertIn("ext_workcore_core", combined)
        self.assertIn("ext_workcore_commercial", combined)
        self.assertIn("Schema::hasColumn", combined)
        self.assertIn("Schema::hasTable", combined)

        for filename, migration in contents.items():
            self.assertIn("Schema::hasTable", migration, filename)
            self.assertNotIn("Schema::create('tz_", migration, filename)


if __name__ == "__main__":
    unittest.main()
