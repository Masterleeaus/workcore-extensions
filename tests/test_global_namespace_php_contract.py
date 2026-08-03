from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
GLOBAL_NAMESPACE_FILES = [
    REPO_ROOT / "native-extensions/WorkCore/database/migrations/2026_08_03_020000_add_workcore_entitlements_to_magicai_plans.php",
    REPO_ROOT / "tools/verify_workcore_entitlement_projection.php",
]


class GlobalNamespacePhpContractTests(unittest.TestCase):
    def test_global_namespace_files_do_not_import_builtin_exceptions(self) -> None:
        for path in GLOBAL_NAMESPACE_FILES:
            content = path.read_text(encoding="utf-8")
            self.assertNotIn("use RuntimeException;", content, path.as_posix())
            self.assertNotIn("use Throwable;", content, path.as_posix())

    def test_global_namespace_files_reference_builtin_exceptions_explicitly(self) -> None:
        migration = GLOBAL_NAMESPACE_FILES[0].read_text(encoding="utf-8")
        verifier = GLOBAL_NAMESPACE_FILES[1].read_text(encoding="utf-8")

        self.assertIn("throw new \\RuntimeException", migration)
        self.assertIn("throw new \\RuntimeException", verifier)
        self.assertIn("catch (\\Throwable", verifier)


if __name__ == "__main__":
    unittest.main()
