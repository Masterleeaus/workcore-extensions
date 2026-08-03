from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
BASE = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Entitlements"
NATIVE = REPO_ROOT / "native-extensions/WorkCore/System"
CONFIG = REPO_ROOT / "native-extensions/WorkCore/config/workcore-native.php"


class WorkCoreMagicAISubscriptionRefreshTests(unittest.TestCase):
    def test_effective_subscription_resolver_contract_is_provider_neutral(self) -> None:
        content = (BASE / "Contracts/EffectiveSubscriptionResolverContract.php").read_text(encoding="utf-8")
        self.assertIn("interface EffectiveSubscriptionResolverContract", content)
        self.assertIn("public function resolve(int $companyId): EffectiveSubscriptionSnapshot", content)

    def test_magicai_resolver_normalizes_status_expiry_and_plan_features(self) -> None:
        content = (NATIVE / "Entitlements/MagicAIEffectiveSubscriptionResolver.php").read_text(encoding="utf-8")
        for token in (
            "implements EffectiveSubscriptionResolverContract",
            "ConnectionInterface",
            "tz_companies",
            "owner_user_id",
            "active_statuses",
            "subscriptions_table",
            "plans_table",
            "source_revision",
            "accessValidUntil",
            "feature_keys",
            "orderByDesc",
            "grantsAccessAt",
        ):
            self.assertIn(token, content)
        self.assertIn("private function normalizeStatus", content)
        self.assertIn("private function noSubscription", content)
        self.assertNotIn("Helper::getCurrentActiveSubscription", content)
        self.assertNotIn("activePlan()", content)

    def test_refresh_service_projects_normalized_subscription(self) -> None:
        content = (BASE / "CompanyEntitlementRefreshService.php").read_text(encoding="utf-8")
        self.assertIn("EffectiveSubscriptionResolverContract", content)
        self.assertIn("WorkCorePlanEntitlementAdapter", content)
        self.assertIn("public function refresh(int $companyId): int", content)
        self.assertIn("$this->subscriptions->resolve($companyId)", content)
        self.assertIn("$this->adapter->project($companyId", content)

    def test_native_command_supports_single_or_all_company_refresh(self) -> None:
        content = (NATIVE / "Console/Commands/RefreshWorkCoreEntitlementsCommand.php").read_text(encoding="utf-8")
        self.assertIn("workcore:refresh-entitlements", content)
        self.assertIn("{company?", content)
        self.assertIn("{--all", content)
        self.assertIn("CompanyEntitlementRefreshService", content)
        self.assertIn("tz_companies", content)
        self.assertIn("status", content)
        self.assertIn("refresh((int) $companyId)", content)

    def test_native_config_declares_normalized_subscription_source_contract(self) -> None:
        content = CONFIG.read_text(encoding="utf-8")
        for token in (
            "'subscription_source'",
            "'subscriptions_table'",
            "'plans_table'",
            "'active_statuses'",
            "'status_map'",
            "'feature_keys'",
            "'valid_until_column'",
        ):
            self.assertIn(token, content)

    def test_native_provider_binds_resolver_refresh_service_and_command(self) -> None:
        content = (NATIVE / "WorkCoreServiceProvider.php").read_text(encoding="utf-8")
        for token in (
            "EffectiveSubscriptionResolverContract::class",
            "MagicAIEffectiveSubscriptionResolver::class",
            "CompanyEntitlementRefreshService::class",
            "RefreshWorkCoreEntitlementsCommand::class",
            "workcore-native.entitlements.subscription_source",
            "$this->commands([",
        ):
            self.assertIn(token, content)


if __name__ == "__main__":
    unittest.main()
