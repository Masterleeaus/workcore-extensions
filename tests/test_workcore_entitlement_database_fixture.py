from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
WORKFLOW = REPO_ROOT / ".github/workflows/magicai-native-laravel10.yml"
VERIFIER = REPO_ROOT / "tools/verify_workcore_entitlement_projection.php"


class WorkCoreEntitlementDatabaseFixtureTests(unittest.TestCase):
    def test_laravel_fixture_runs_database_backed_entitlement_verifier(self) -> None:
        workflow = WORKFLOW.read_text(encoding="utf-8")
        self.assertIn("verify_workcore_entitlement_projection.php", workflow)
        self.assertIn("matrix.profile == 'full'", workflow)
        self.assertIn("Run database-backed entitlement projection fixture", workflow)

    def test_verifier_exercises_real_native_bindings_and_revision_lifecycle(self) -> None:
        content = VERIFIER.read_text(encoding="utf-8")
        for token in (
            "CompanyEntitlementRefreshService::class",
            "EntitlementResolverContract::class",
            "EntitlementRevisionResolverContract::class",
            "EffectiveSubscriptionResolverContract::class",
            "Schema::create('plans'",
            "Schema::create('subscriptions'",
            "ext_workcore_core",
            "ext_workcore_commercial",
            "workcore.crm",
            "workcore.finance",
            "first_revision",
            "stable_revision",
            "changed_revision",
            "expired_revision",
        ):
            self.assertIn(token, content)
        self.assertIn("$refresh->refresh($companyId)", content)
        self.assertIn("$entitlements->allows($companyId, 'workcore.crm')", content)
        self.assertIn("$entitlements->allows($companyId, 'workcore.finance')", content)
        self.assertIn("$revisions->revision($companyId)", content)
        self.assertIn("RuntimeException", content)


if __name__ == "__main__":
    unittest.main()
