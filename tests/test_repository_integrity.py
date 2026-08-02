from __future__ import annotations

import json
import unittest
from pathlib import Path

from tools.validate_repository import EXPECTED_PACKAGES, validate_repository


REPO_ROOT = Path(__file__).resolve().parents[1]


class RepositoryIntegrityTests(unittest.TestCase):
    def test_repository_validation_passes_for_extracted_packages(self) -> None:
        report = validate_repository(REPO_ROOT)
        self.assertEqual([], report.errors)
        self.assertEqual(6, report.package_count)
        self.assertEqual(35, report.module_count)
        self.assertEqual(2158, report.owned_file_count)

    def test_exact_package_set_is_present(self) -> None:
        actual = {path.name for path in (REPO_ROOT / 'packages').iterdir() if path.is_dir()}
        self.assertEqual(EXPECTED_PACKAGES, actual)

    def test_every_domain_extension_requires_shared_foundation(self) -> None:
        for package_name in EXPECTED_PACKAGES - {'workcore-shared-foundation'}:
            extension = json.loads(
                (REPO_ROOT / 'packages' / package_name / 'extension.json').read_text(encoding='utf-8')
            )
            self.assertEqual(['workcore/shared-foundation'], extension['requires'])
            self.assertTrue(extension['canonical_namespace_preserved'])
            self.assertFalse(extension['destructive_uninstall'])


if __name__ == '__main__':
    unittest.main()
