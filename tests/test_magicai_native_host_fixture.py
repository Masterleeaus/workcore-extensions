from __future__ import annotations

import json
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]


class MagicAINativeLaravel10FixtureTests(unittest.TestCase):
    def test_workflow_creates_real_laravel_10_host_and_runs_native_matrix(self) -> None:
        workflow = (REPO_ROOT / '.github/workflows/magicai-native-laravel10.yml').read_text(encoding='utf-8')
        required = [
            'composer create-project laravel/laravel:^10.0',
            'python tools/build_magicai_extensions.py',
            'python tools/prepare_magicai_10_fixture.py',
            'php tools/verify_magicai_native_host.php',
            'php artisan migrate:fresh',
            'parent-absent-commercial',
            'WORKCORE_ENABLED=false',
            'php-version: \'8.2\'',
        ]
        for token in required:
            self.assertIn(token, workflow)

    def test_fixture_profiles_cover_parent_addons_full_disabled_and_parent_absent(self) -> None:
        profiles = json.loads(
            (REPO_ROOT / 'integration/magicai-10-fixture/profiles.json').read_text(encoding='utf-8')
        )
        self.assertEqual([], profiles['parent']['loaded_modules'])
        self.assertEqual(['finance', 'payroll', 'inventory', 'supply', 'vault', 'trust_accounting'], profiles['commercial']['loaded_modules'])
        self.assertEqual(34, len(profiles['full']['loaded_modules']))
        self.assertFalse(profiles['disabled']['workcore_enabled'])
        self.assertNotIn('WorkCore', profiles['parent-absent-commercial']['folders'])
        self.assertEqual([], profiles['parent-absent-commercial']['loaded_modules'])

    def test_fixture_provider_reproduces_magicai_class_exists_provider_map_loading(self) -> None:
        provider = (
            REPO_ROOT
            / 'integration/magicai-10-fixture/app/Providers/MagicAIExtensionFixtureServiceProvider.php'
        ).read_text(encoding='utf-8')
        self.assertIn('class_exists($provider)', provider)
        self.assertIn('$this->app->register($provider)', provider)
        self.assertIn('MAGICAI_EXTENSION_PROVIDERS', provider)

    def test_verifier_checks_exact_loaded_modules_middleware_and_dormant_parent_absence(self) -> None:
        verifier = (REPO_ROOT / 'tools/verify_magicai_native_host.php').read_text(encoding='utf-8')
        required = [
            'WorkModuleRegistry::class',
            "'workcore.tenant'",
            "'workcore.capability'",
            "'workcore.api'",
            'loaded_modules',
            'parent_absent',
            'workcore_enabled',
        ]
        for token in required:
            self.assertIn(token, verifier)

    def test_native_enable_flags_are_environment_driven_and_boot_is_guarded(self) -> None:
        parent_config = (REPO_ROOT / 'native-extensions/WorkCore/config/workcore-native.php').read_text(encoding='utf-8')
        parent_provider = (REPO_ROOT / 'native-extensions/WorkCore/System/WorkCoreServiceProvider.php').read_text(encoding='utf-8')
        self.assertIn("env('WORKCORE_NATIVE_ENABLED', true)", parent_config)
        self.assertGreaterEqual(parent_provider.count("config('workcore-native.enabled', true)"), 2)

        for config in sorted((REPO_ROOT / 'native-extensions').glob('WorkCore*/config/*.php')):
            if config == REPO_ROOT / 'native-extensions/WorkCore/config/workcore-native.php':
                continue
            content = config.read_text(encoding='utf-8')
            self.assertIn("env('", content)
            self.assertIn('_ENABLED', content)


if __name__ == '__main__':
    unittest.main()
