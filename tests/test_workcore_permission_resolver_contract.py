from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
NATIVE_RESOLVER = (
    REPO_ROOT
    / "native-extensions/WorkCore/System/Resolvers/WorkCorePermissionResolver.php"
)
HOST_OVERLAY_RESOLVER = (
    REPO_ROOT
    / "integration/host-overlay/app/Support/WorkCore/WorkCorePermissionResolver.php"
)
ACCESS_LEVEL_ENUM = (
    REPO_ROOT
    / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Authorization/WorkCoreAccessLevel.php"
)


class WorkCorePermissionResolverContractTests(unittest.TestCase):
    def test_privileged_memberships_use_a_declared_access_level(self) -> None:
        enum_source = ACCESS_LEVEL_ENUM.read_text(encoding="utf-8")
        self.assertIn("case All = 'all';", enum_source)
        self.assertNotIn("case Manage", enum_source)

        for resolver_path in (NATIVE_RESOLVER, HOST_OVERLAY_RESOLVER):
            resolver_source = resolver_path.read_text(encoding="utf-8")
            self.assertNotIn(
                "WorkCoreAccessLevel::Manage",
                resolver_source,
                resolver_path.as_posix(),
            )
            self.assertIn(
                "return WorkCoreAccessLevel::All;",
                resolver_source,
                resolver_path.as_posix(),
            )


if __name__ == "__main__":
    unittest.main()
