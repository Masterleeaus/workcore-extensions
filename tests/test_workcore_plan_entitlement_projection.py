from __future__ import annotations

import re
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
BASE = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System"
WORKCORE_CONFIG = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/Config/workcore.php"
MIGRATION = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/Database/Migrations/2026_08_03_010000_create_tz_company_entitlement_projection_tables.php"
NATIVE_CONFIG = REPO_ROOT / "native-extensions/WorkCore/config/workcore-native.php"
NATIVE_PROVIDER = REPO_ROOT / "native-extensions/WorkCore/System/WorkCoreServiceProvider.php"


class WorkCorePlanEntitlementProjectionTests(unittest.TestCase):
    def test_normalized_subscription_snapshot_is_provider_neutral_and_time_aware(self) -> None:
        content = (BASE / "Entitlements/EffectiveSubscriptionSnapshot.php").read_text(encoding="utf-8")
        self.assertIn("final readonly class EffectiveSubscriptionSnapshot", content)
        for token in (
            "public string $subscriptionId",
            "public string $planId",
            "public string $status",
            "public array $features",
            "public ?DateTimeImmutable $accessValidUntil",
            "public string $sourceRevision",
            "public function grantsAccessAt(DateTimeImmutable $at): bool",
            "public function featureEnabled(string $key): bool",
        ):
            self.assertIn(token, content)
        self.assertNotIn("Stripe", content)
        self.assertNotIn("PayPal", content)

    def test_adapter_projects_complete_capability_state_and_increments_revision_atomically(self) -> None:
        content = (BASE / "Entitlements/WorkCorePlanEntitlementAdapter.php").read_text(encoding="utf-8")
        for token in (
            "ConnectionInterface",
            "CapabilityRegistry",
            "public function project(int $companyId, EffectiveSubscriptionSnapshot $subscription): int",
            "$this->db->transaction",
            "insertOrIgnore",
            "lockForUpdate()",
            "array_fill_keys(array_keys($this->capabilities->all()), false)",
            "tz_company_entitlement_states",
            "tz_company_entitlement_projections",
            "source_checksum",
            "source_revision",
            "revision",
            "updateOrInsert",
            "->delete()",
        ):
            self.assertIn(token, content)
        self.assertLess(content.index("insertOrIgnore"), content.index("lockForUpdate()"))
        self.assertIn("$subscription->grantsAccessAt", content)
        self.assertIn("$this->capabilities->has", content)

    def test_projected_resolver_fails_closed_for_optional_capabilities(self) -> None:
        content = (BASE / "Entitlements/ProjectedCompanyEntitlementResolver.php").read_text(encoding="utf-8")
        for token in (
            "implements EntitlementResolverContract",
            "CapabilityRegistry",
            "tz_company_entitlement_states",
            "tz_company_entitlement_projections",
            "bootstrapCapabilities",
            "valid_until",
            "enabled",
            "return false",
        ):
            self.assertIn(token, content)
        self.assertIn("! $this->capabilities->has($capability)", content)
        self.assertIn("in_array($capability, $this->bootstrapCapabilities, true)", content)

    def test_revision_contract_exposes_company_revision_without_raw_subscription_data(self) -> None:
        contract = (BASE / "Actions/Contracts/EntitlementRevisionResolverContract.php").read_text(encoding="utf-8")
        resolver = (BASE / "Entitlements/ProjectedCompanyEntitlementResolver.php").read_text(encoding="utf-8")
        self.assertIn("public function revision(int $companyId): int", contract)
        self.assertIn("implements EntitlementResolverContract, EntitlementRevisionResolverContract", resolver)
        self.assertIn("public function revision(int $companyId): int", resolver)

    def test_projection_schema_is_company_scoped_revisioned_and_expirable(self) -> None:
        content = MIGRATION.read_text(encoding="utf-8")
        self.assertIn("Schema::create('tz_company_entitlement_states'", content)
        self.assertIn("Schema::create('tz_company_entitlement_projections'", content)
        for token in (
            "company_id",
            "capability_key",
            "revision",
            "source_subscription_id",
            "source_plan_id",
            "source_revision",
            "source_checksum",
            "valid_from",
            "valid_until",
            "projected_at",
            "metadata",
        ):
            self.assertIn(token, content)
        self.assertIn("unique(['company_id', 'capability_key']", content)

    def test_native_package_binds_projection_resolver_and_plan_adapter(self) -> None:
        config = NATIVE_CONFIG.read_text(encoding="utf-8")
        provider = NATIVE_PROVIDER.read_text(encoding="utf-8")
        for token in (
            "'entitlements'",
            "'feature_map'",
            "'bootstrap_capabilities'",
            "'ext_workcore_core'",
            "'ext_workcore_operations'",
            "'ext_workcore_workforce'",
            "'ext_workcore_resources'",
            "'ext_workcore_commercial'",
            "'ext_workcore_ai_actions'",
        ):
            self.assertIn(token, config)
        for token in (
            "ProjectedCompanyEntitlementResolver::class",
            "WorkCorePlanEntitlementAdapter::class",
            "EntitlementResolverContract::class",
            "EntitlementRevisionResolverContract::class",
            "workcore-native.entitlements.feature_map",
            "workcore-native.entitlements.bootstrap_capabilities",
        ):
            self.assertIn(token, provider)

    def test_native_feature_map_covers_every_registered_capability(self) -> None:
        workcore = WORKCORE_CONFIG.read_text(encoding="utf-8")
        capability_section = workcore.split("'capabilities' => [", 1)[1].split("    'storage' => [", 1)[0]
        registered = set(re.findall(r"^\s{8}'(workcore\.[^']+)'\s*=>\s*\[", capability_section, re.MULTILINE))

        native = NATIVE_CONFIG.read_text(encoding="utf-8")
        entitlement_section = native.split("'entitlements' => [", 1)[1]
        projection_section = entitlement_section.split("'subscription_source' => [", 1)[0]
        configured = set(re.findall(r"'(workcore\.[^']+)'", projection_section))

        self.assertTrue(registered)
        self.assertEqual(registered, configured, f"Missing or unknown entitlement capabilities: {sorted(registered ^ configured)}")


if __name__ == "__main__":
    unittest.main()
