from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
BRANCH_TARGETS = {
    "native_config": REPO_ROOT / "native-extensions/WorkCore/config/workcore-native.php",
    "native_provider": REPO_ROOT / "native-extensions/WorkCore/System/WorkCoreServiceProvider.php",
    "native_commercial_provider": REPO_ROOT / "native-extensions/WorkCoreCommercial/System/WorkCoreCommercialServiceProvider.php",
    "standalone_config": REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/Config/workcore.php",
    "finance_provider": REPO_ROOT / "packages/workcore-commercial/src/Domains/WorkCore/System/Modules/Finance/WorkCoreFinanceServiceProvider.php",
    "host_routes": REPO_ROOT / "integration/host-overlay/routes/api.php",
}


class WorkCoreMagicAIAuthenticationContractTests(unittest.TestCase):
    def test_native_wrapper_declares_passport_middleware(self) -> None:
        config = BRANCH_TARGETS["native_config"].read_text(encoding="utf-8")
        self.assertIn("'api_middleware'", config)
        self.assertIn("'auth:api'", config)
        self.assertNotIn("'auth:sanctum'", config)

    def test_native_wrapper_overrides_runtime_middleware_after_kernel_registration(self) -> None:
        provider = BRANCH_TARGETS["native_provider"].read_text(encoding="utf-8")
        register_token = "$this->app->register(\\App\\Domains\\WorkCore\\WorkCoreServiceProvider::class);"
        override_token = "$this->app['config']->set('workcore.api.middleware'"
        self.assertIn(register_token, provider)
        self.assertIn(override_token, provider)
        self.assertLess(provider.index(register_token), provider.index(override_token))
        self.assertIn("config('workcore-native.api_middleware'", provider)

    def test_standalone_runtime_keeps_sanctum_default(self) -> None:
        config = BRANCH_TARGETS["standalone_config"].read_text(encoding="utf-8")
        self.assertIn("'auth:sanctum'", config)

    def test_native_commercial_disables_sanctum_only_direct_finance_routes(self) -> None:
        native_provider = BRANCH_TARGETS["native_commercial_provider"].read_text(encoding="utf-8")
        finance_provider = BRANCH_TARGETS["finance_provider"].read_text(encoding="utf-8")
        disable_token = "$this->app['config']->set('workcore.finance.routes_enabled', false);"
        load_token = "$registry->loadMany(self::MODULES);"
        self.assertIn("Route::middleware(['api', 'auth:sanctum'])", finance_provider)
        self.assertIn(disable_token, native_provider)
        self.assertIn(load_token, native_provider)
        self.assertLess(native_provider.index(disable_token), native_provider.index(load_token))

    def test_magicai_host_overlay_uses_passport(self) -> None:
        routes = BRANCH_TARGETS["host_routes"].read_text(encoding="utf-8")
        self.assertIn("'auth:api'", routes)
        self.assertNotIn("'auth:sanctum'", routes)


if __name__ == "__main__":
    unittest.main()
