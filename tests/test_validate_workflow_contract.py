from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = REPO_ROOT / ".github/workflows/validate.yml"


class ValidateWorkflowContractTests(unittest.TestCase):
    def test_php_lint_count_is_derived_from_checksum_manifests(self) -> None:
        content = WORKFLOW.read_text(encoding="utf-8")

        self.assertNotIn('test "$php_files" -eq 2090', content)
        self.assertIn("packages/*/files.sha256.json", content)
        self.assertIn("expected_php_files", content)
        self.assertIn("actual_php_files", content)
        self.assertIn('test "$actual_php_files" -eq "$expected_php_files"', content)
        self.assertIn("entry.get('path', '').endswith('.php')", content)
        self.assertIn("xargs -0 -n1 -P4 php -l", content)


if __name__ == "__main__":
    unittest.main()
