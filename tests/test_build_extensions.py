from pathlib import Path
import hashlib
import subprocess
import sys
import tempfile
import unittest

from tools.build_extensions import GROUPS, build, discover_modules, validate_ownership
from tools.build_site import build_site

SOURCE_ROOT = Path('/mnt/data/workcore_magicai_consolidated_scan')


class OwnershipTests(unittest.TestCase):
    def test_discovers_all_35_module_directories(self) -> None:
        self.assertEqual(35, len(discover_modules(SOURCE_ROOT)))

    def test_every_module_has_exactly_one_owner(self) -> None:
        result = validate_ownership(SOURCE_ROOT)
        self.assertEqual([], result['missing'])
        self.assertEqual({}, result['duplicates'])
        self.assertEqual(35, result['assigned_count'])

    def test_defines_exactly_five_domain_groups(self) -> None:
        self.assertEqual(
            {
                'business-network',
                'commercial',
                'work-operations',
                'property-operations',
                'workforce-assurance',
            },
            set(GROUPS),
        )


class PackageBuildTests(unittest.TestCase):
    def test_build_creates_shared_and_five_group_packages(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            packages = output_root / 'packages'
            self.assertTrue((packages / 'workcore-shared-foundation').is_dir())
            for group in GROUPS:
                self.assertTrue((packages / f'workcore-{group}').is_dir())

    def test_build_copies_each_owned_module_to_its_group(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            for group, definition in GROUPS.items():
                modules_root = (
                    output_root / 'packages' / f'workcore-{group}' /
                    'src/Domains/WorkCore/System/Modules'
                )
                self.assertEqual(
                    set(definition['modules']),
                    {path.name for path in modules_root.iterdir() if path.is_dir()},
                )

    def test_shared_foundation_keeps_historical_migrations(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            migration_root = (
                output_root / 'packages/workcore-shared-foundation/'
                'src/Domains/WorkCore/Database/Migrations'
            )
            self.assertGreaterEqual(len(list(migration_root.glob('*.php'))), 100)


class ProviderIsolationTests(unittest.TestCase):
    def test_shared_provider_skips_unavailable_module_providers_and_has_no_fallback_loader(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            provider = (
                output_root / 'packages/workcore-shared-foundation/'
                'src/Domains/WorkCore/WorkCoreServiceProvider.php'
            ).read_text()
            self.assertIn("if (! class_exists($provider))", provider)
            self.assertNotIn("foreach (array_keys($registry->all()) as $key)", provider)

    def test_group_providers_load_their_complete_runtime_key_sets(self) -> None:
        provider_files = {
            'business-network': 'BusinessNetworkServiceProvider.php',
            'commercial': 'CommercialServiceProvider.php',
            'work-operations': 'WorkOperationsServiceProvider.php',
            'property-operations': 'PropertyOperationsServiceProvider.php',
            'workforce-assurance': 'WorkforceAssuranceServiceProvider.php',
        }
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            for group, filename in provider_files.items():
                provider = (
                    output_root / 'packages' / f'workcore-{group}' /
                    'src/Domains/WorkCore/Providers' / filename
                ).read_text()
                for runtime_key in GROUPS[group]['runtime_keys']:
                    self.assertIn(f"'{runtime_key}'", provider)


class ReleaseIntegrityTests(unittest.TestCase):
    def test_build_emits_ownership_manifest_and_package_checksums(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            self.assertTrue((output_root / 'dist/ownership-manifest.json').is_file())
            for package in ['workcore-shared-foundation', *[f'workcore-{group}' for group in GROUPS]]:
                self.assertTrue((output_root / 'packages' / package / 'files.sha256.json').is_file())

    def test_build_emits_six_package_zips_and_workspace_zip(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            dist = output_root / 'dist'
            expected = {'workcore-shared-foundation.zip', 'workcore-extension-workspace.zip'}
            expected.update({f'workcore-{group}.zip' for group in GROUPS})
            self.assertTrue(expected.issubset({path.name for path in dist.glob('*.zip')}))

    def test_every_original_module_file_is_preserved_in_its_owner_package(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            output_root = Path(temporary_directory)
            build(SOURCE_ROOT, output_root)
            source_modules = SOURCE_ROOT / 'app/Domains/WorkCore/System/Modules'
            for group, definition in GROUPS.items():
                destination_modules = (
                    output_root / 'packages' / f'workcore-{group}' /
                    'src/Domains/WorkCore/System/Modules'
                )
                for module in definition['modules']:
                    for source_file in (source_modules / module).rglob('*'):
                        if source_file.is_file():
                            relative = source_file.relative_to(source_modules)
                            destination_file = destination_modules / relative
                            self.assertTrue(destination_file.is_file(), str(destination_file))
                            self.assertEqual(source_file.read_bytes(), destination_file.read_bytes())


class SiteCatalogueTests(unittest.TestCase):
    def test_site_builder_cli_can_run_directly(self) -> None:
        result = subprocess.run(
            [sys.executable, 'tools/build_site.py', '--help'],
            cwd='/mnt/data/workcore-extensions',
            capture_output=True,
            text=True,
        )
        self.assertEqual(0, result.returncode, result.stderr)

    def test_site_archive_is_deterministic(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            temporary_root = Path(temporary_directory)
            first_zip = temporary_root / 'first.zip'
            second_zip = temporary_root / 'second.zip'
            build_site(
                Path('/mnt/data/workcore-extensions'),
                temporary_root / 'site-one',
                zip_path=first_zip,
            )
            build_site(
                Path('/mnt/data/workcore-extensions'),
                temporary_root / 'site-two',
                zip_path=second_zip,
            )
            self.assertEqual(
                hashlib.sha256(first_zip.read_bytes()).hexdigest(),
                hashlib.sha256(second_zip.read_bytes()).hexdigest(),
            )

    def test_site_builder_creates_multi_page_catalogue_and_downloads(self) -> None:
        with tempfile.TemporaryDirectory() as temporary_directory:
            site_root = Path(temporary_directory) / 'site'
            build_site(
                Path('/mnt/data/workcore-extensions'),
                site_root,
                zip_path=Path(temporary_directory) / 'site.zip',
            )
            for page in ['index.html', 'packages.html', 'architecture.html']:
                self.assertTrue((site_root / page).is_file())
            for archive in ['workcore-shared-foundation.zip', *[f'workcore-{group}.zip' for group in GROUPS]]:
                self.assertTrue((site_root / 'downloads' / archive).is_file())


if __name__ == '__main__':
    unittest.main()
