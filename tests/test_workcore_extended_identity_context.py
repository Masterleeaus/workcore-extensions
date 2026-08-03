from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
BASE = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System"
NATIVE_RESOLVER = REPO_ROOT / "native-extensions/WorkCore/System/Resolvers/WorkCoreTenantResolver.php"
HOST_RESOLVER = REPO_ROOT / "integration/host-overlay/app/Support/WorkCore/WorkCoreTenantResolver.php"


class WorkCoreExtendedIdentityContextTests(unittest.TestCase):
    def test_operation_snapshot_carries_extended_identity_and_security_context(self) -> None:
        content = (BASE / "Context/OperationContextSnapshot.php").read_text(encoding="utf-8")
        for token in (
            "public string $actorSubject",
            "public ?int $workerId",
            "public ?int $branchId",
            "public ?int $territoryId",
            "public ?string $deviceId",
            "public string $authenticationAssurance",
            "public int $securityRevision",
            "public int $membershipRevision",
            "'actor_subject'",
            "'worker_id'",
            "'branch_id'",
            "'territory_id'",
            "'device_id'",
            "'authentication_assurance'",
            "'security_revision'",
            "'membership_revision'",
        ):
            self.assertIn(token, content)
        self.assertIn("fromArray", content)

    def test_operation_context_contract_and_runtime_expose_extended_fields(self) -> None:
        contract = (BASE / "Contracts/OperationContextContract.php").read_text(encoding="utf-8")
        runtime = (BASE / "Context/OperationContext.php").read_text(encoding="utf-8")
        for token in (
            "actorSubject(): string",
            "workerId(): ?int",
            "branchId(): ?int",
            "territoryId(): ?int",
            "deviceId(): ?string",
            "authenticationAssurance(): string",
            "securityRevision(): int",
            "membershipRevision(): int",
        ):
            self.assertIn(token, contract)
            self.assertIn(token, runtime)
        self.assertIn("actorSubject:", runtime)
        self.assertIn("membershipRevision:", runtime)

    def test_identity_enricher_uses_authoritative_membership_worker_and_territory_data(self) -> None:
        content = (BASE / "Identity/WorkCoreIdentityContextResolver.php").read_text(encoding="utf-8")
        for token in (
            "tz_company_memberships",
            "tz_company_member_permissions",
            "tz_company_role_permissions",
            "tz_workers",
            "tz_territory_assignments",
            "tz_territories",
            "tz_branches",
            "X-Titan-Branch",
            "X-Titan-Territory",
            "X-Titan-Device",
            "authentication_assurance",
            "membership_revision",
            "security_revision",
            "actor_subject",
            "worker_id",
            "branch_id",
            "territory_id",
        ):
            self.assertIn(token, content)
        self.assertIn("abort(409", content)
        self.assertIn("where('company_id', $companyId)", content)
        self.assertIn("where('user_id', $userId)", content)

    def test_native_and_host_tenant_resolvers_delegate_identity_enrichment(self) -> None:
        for path in (NATIVE_RESOLVER, HOST_RESOLVER):
            content = path.read_text(encoding="utf-8")
            self.assertIn("WorkCoreIdentityContextResolver", content)
            self.assertIn("$this->identity->resolve(", content)
            self.assertNotIn("return ['company_id' => $companyId, 'user_id' => $userId]", content)

    def test_tenant_middleware_sets_extended_operation_context(self) -> None:
        content = (BASE / "Tenancy/ResolveWorkCoreTenant.php").read_text(encoding="utf-8")
        for token in (
            "$identity['actor_subject']",
            "$identity['worker_id']",
            "$identity['branch_id']",
            "$identity['territory_id']",
            "$identity['device_id']",
            "$identity['authentication_assurance']",
            "$identity['security_revision']",
            "$identity['membership_revision']",
        ):
            self.assertIn(token, content)

    def test_queue_context_round_trip_remains_backward_compatible(self) -> None:
        snapshot = (BASE / "Context/OperationContextSnapshot.php").read_text(encoding="utf-8")
        restore = (BASE / "Queue/RestoreWorkCoreContext.php").read_text(encoding="utf-8")
        self.assertIn("$payload['actor_subject'] ??", snapshot)
        self.assertIn("$payload['security_revision'] ?? 0", snapshot)
        self.assertIn("OperationContextSnapshot::fromArray", restore)


if __name__ == "__main__":
    unittest.main()
