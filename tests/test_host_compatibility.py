from __future__ import annotations

import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

from tools.build_extensions import PACKAGE_VERSION, build
from tools.generate_host_profile import generate_host_composer, load_profiles

REPOSITORY_ROOT = Path(__file__).resolve().parents[1]
SOURCE_ROOT = Path(os.environ.get('WORKCORE_SOURCE_ROOT', '/mnt/data/workcore_magicai_consolidated_scan'))
PACKAGES_ROOT = REPOSITORY_ROOT / 'packages'


class OptionalConfigurationTests(unittest.TestCase):
    def test_shared_foundation_does_not_unconditionally_require_commercial_config(self) -> None:
        config = (
            PACKAGES_ROOT / 'workcore-shared-foundation/'
            'src/Domains/WorkCore/Config/workcore.php'
        ).read_text(encoding='utf-8')

        self.assertNotIn(
            "$financeConfig = require __DIR__ . '/../System/Modules/Finance/config/titan-money.php';",
            config,
        )
        self.assertIn('is_file($financeConfigPath)', config)
        self.assertIn('is_file($financePermissionsPath)', config)


class MigrationPortabilityTests(unittest.TestCase):
    def test_ai_knowledge_fulltext_index_is_guarded_for_sqlite(self) -> None:
        migration = (
            PACKAGES_ROOT / 'workcore-shared-foundation/'
            'src/Domains/WorkCore/Database/Migrations/'
            '2026_07_23_120058_create_tz_ai_knowledge_tables.php'
        ).read_text(encoding='utf-8')

        self.assertIn("Schema::getConnection()->getDriverName()", migration)
        self.assertIn("['mysql', 'mariadb', 'pgsql']", migration)
        self.assertIn('if ($supportsFullText)', migration)
        self.assertIn("$table->fullText('content', 'ai_kchunk_content_ft');", migration)


class HostOverlayMigrationTests(unittest.TestCase):
    def test_meetup_tenancy_migration_guards_optional_donor_tables(self) -> None:
        migration = (
            REPOSITORY_ROOT / 'integration/host-overlay/database/migrations/'
            '2026_07_25_000001_add_workcore_tenancy_to_meetup.php'
        ).read_text(encoding='utf-8')

        for table in ['conversations', 'participants', 'messages']:
            self.assertIn(f"Schema::hasTable('{table}')", migration)
        self.assertIn("Schema::hasColumn('users', 'active_company_id')", migration)


class ComposerPackageTests(unittest.TestCase):
    def test_all_packages_share_an_explicit_release_version(self) -> None:
        for composer_path in sorted(PACKAGES_ROOT.glob('*/composer.json')):
            composer = json.loads(composer_path.read_text(encoding='utf-8'))
            self.assertEqual(PACKAGE_VERSION, composer['version'])

    def test_domain_packages_publish_optional_integration_hints(self) -> None:
        for package_root in sorted(PACKAGES_ROOT.glob('workcore-*')):
            if package_root.name == 'workcore-shared-foundation':
                continue
            composer = json.loads((package_root / 'composer.json').read_text(encoding='utf-8'))
            extension = json.loads((package_root / 'extension.json').read_text(encoding='utf-8'))
            self.assertEqual(['workcore/shared-foundation'], extension['requires'])
            self.assertEqual(sorted(composer.get('suggest', {})), sorted(extension['integrates_with']))

    def test_shared_foundation_is_laravel_discoverable(self) -> None:
        composer = json.loads((
            PACKAGES_ROOT / 'workcore-shared-foundation/composer.json'
        ).read_text(encoding='utf-8'))

        self.assertEqual(
            ['App\\Domains\\WorkCore\\WorkCoreServiceProvider'],
            composer['extra']['laravel']['providers'],
        )
        self.assertEqual('^11.0 || ^12.0', composer['require']['laravel/framework'])


class InstallProfileTests(unittest.TestCase):
    def test_profiles_only_reference_known_packages_and_include_foundation(self) -> None:
        profiles = load_profiles(REPOSITORY_ROOT / 'compatibility/install-profiles.json')
        known = {
            'workcore/shared-foundation',
            'workcore/business-network',
            'workcore/commercial',
            'workcore/work-operations',
            'workcore/property-operations',
            'workcore/workforce-assurance',
        }
        for profile_name, profile in profiles.items():
            packages = set(profile['packages'])
            self.assertIn('workcore/shared-foundation', packages, profile_name)
            self.assertTrue(packages <= known, profile_name)

    def test_host_profile_generator_adds_path_repositories_and_selected_requirements(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_path = Path(temporary_directory) / 'composer.json'
            generated = generate_host_composer(
                host_composer=REPOSITORY_ROOT / 'integration/host-overlay/composer.json',
                packages_root=PACKAGES_ROOT,
                profiles_path=REPOSITORY_ROOT / 'compatibility/install-profiles.json',
                profile_name='commercial',
                output_path=output_path,
            )

            self.assertEqual(PACKAGE_VERSION, generated['require']['workcore/shared-foundation'])
            self.assertEqual(PACKAGE_VERSION, generated['require']['workcore/commercial'])
            self.assertNotIn('workcore/business-network', generated['require'])
            repository_urls = {repository['url'] for repository in generated['repositories']}
            self.assertIn('../../packages/workcore-shared-foundation', repository_urls)
            self.assertIn('../../packages/workcore-commercial', repository_urls)
            self.assertTrue(output_path.is_file())

    def test_host_overlay_relies_on_package_discovery_not_embedded_provider_registration(self) -> None:
        providers = (REPOSITORY_ROOT / 'integration/host-overlay/bootstrap/providers.php').read_text(encoding='utf-8')
        self.assertNotIn('App\\Domains\\WorkCore\\WorkCoreServiceProvider::class', providers)

    @unittest.skipUnless(SOURCE_ROOT.is_dir(), 'Consolidated source archive is not available in this environment.')
    def test_builder_removes_embedded_provider_registration_from_host_overlay(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root, include_host_overlay=True)
            providers = (output_root / 'integration/host-overlay/bootstrap/providers.php').read_text(encoding='utf-8')
            self.assertNotIn('App\\Domains\\WorkCore\\WorkCoreServiceProvider::class', providers)


    @unittest.skipUnless(SOURCE_ROOT.is_dir(), 'Consolidated source archive is not available in this environment.')
    def test_builder_guards_optional_meetup_tables_in_host_overlay(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root, include_host_overlay=True)
            migration = (
                output_root / 'integration/host-overlay/database/migrations/'
                '2026_07_25_000001_add_workcore_tenancy_to_meetup.php'
            ).read_text(encoding='utf-8')
            for table in ['conversations', 'participants', 'messages']:
                self.assertIn(f"Schema::hasTable('{table}')", migration)


class DisableAndDependencySafetyTests(unittest.TestCase):
    def test_shared_provider_stops_before_registering_modules_when_disabled(self) -> None:
        provider = (
            PACKAGES_ROOT / 'workcore-shared-foundation/'
            'src/Domains/WorkCore/WorkCoreServiceProvider.php'
        ).read_text(encoding='utf-8')
        disabled_gate = provider.index("if (! (bool) config('workcore.enabled', true))")
        module_registration = provider.index('$this->registerModules();')
        self.assertLess(disabled_gate, module_registration)

    def test_packages_have_no_uninstall_hooks_and_are_non_destructive(self) -> None:
        for package_root in sorted(PACKAGES_ROOT.iterdir()):
            composer = json.loads((package_root / 'composer.json').read_text(encoding='utf-8'))
            scripts = composer.get('scripts', {})
            self.assertNotIn('pre-package-uninstall', scripts, package_root.name)
            self.assertNotIn('post-package-uninstall', scripts, package_root.name)
            self.assertFalse(composer['extra']['workcore']['destructive_uninstall'])

    def test_shared_foundation_has_no_direct_optional_module_includes(self) -> None:
        from tools.analyze_package_dependencies import find_optional_path_includes

        findings = find_optional_path_includes(PACKAGES_ROOT / 'workcore-shared-foundation')
        self.assertEqual([], findings)


class HostProfileCliTests(unittest.TestCase):
    def test_generator_cli_runs_directly(self) -> None:
        result = subprocess.run(
            [sys.executable, 'tools/generate_host_profile.py', '--help'],
            cwd=REPOSITORY_ROOT,
            capture_output=True,
            text=True,
        )
        self.assertEqual(0, result.returncode, result.stderr)


class HostWorkflowTests(unittest.TestCase):
    def test_host_compatibility_workflow_covers_supported_profiles_and_upgrade(self) -> None:
        workflow = (REPOSITORY_ROOT / '.github/workflows/host-compatibility.yml').read_text(encoding='utf-8')
        for profile in ['foundation', 'commercial', 'business-operations', 'property-workforce', 'full']:
            self.assertIn(profile, workflow)
        self.assertIn('upgrade-foundation-to-full', workflow)


if __name__ == '__main__':
    unittest.main()
