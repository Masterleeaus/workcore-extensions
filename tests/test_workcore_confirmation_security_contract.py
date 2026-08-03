from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]


class WorkCoreConfirmationSecurityContractTests(unittest.TestCase):
    def test_confirmation_contract_has_two_phase_verification_and_consumption(self) -> None:
        contract = (REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Actions/Contracts/ConfirmationVerifierContract.php").read_text(encoding="utf-8")
        explicit = (REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Actions/Policies/ExplicitConfirmationVerifier.php").read_text(encoding="utf-8")
        bound = (REPO_ROOT / "packages/workcore-business-network/src/Domains/WorkCore/System/Intelligence/Approvals/BoundConfirmationVerifier.php").read_text(encoding="utf-8")
        for content in (contract, explicit, bound):
            self.assertIn("public function verify(ActionDefinition $definition, ActionRequest $request): bool", content)
            self.assertIn("public function consume(ActionDefinition $definition, ActionRequest $request): bool", content)

    def test_bound_verifier_does_not_consume_during_verify(self) -> None:
        bound = (REPO_ROOT / "packages/workcore-business-network/src/Domains/WorkCore/System/Intelligence/Approvals/BoundConfirmationVerifier.php").read_text(encoding="utf-8")
        verify_body, consume_body = bound.split("public function consume", maxsplit=1)
        self.assertNotIn("$this->nonces->consume", verify_body)
        self.assertIn("$this->nonces->consume", consume_body)
        self.assertIn("$request->payloadHash()", verify_body)
        self.assertIn("$request->idempotencyKey", verify_body)

    def test_dispatcher_consumes_confirmation_inside_action_transaction(self) -> None:
        dispatcher = (REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Actions/BusinessActionDispatcher.php").read_text(encoding="utf-8")
        replay = "$this->idempotency->replay($request)"
        transaction = "$this->db->transaction(function ()"
        consume = "$this->confirmations->consume($definition, $request)"
        reserve = "$this->idempotency->reserve($request)"
        self.assertIn(replay, dispatcher)
        self.assertIn(transaction, dispatcher)
        self.assertIn(consume, dispatcher)
        self.assertIn(reserve, dispatcher)
        self.assertLess(dispatcher.index(replay), dispatcher.index(transaction))
        self.assertGreater(dispatcher.index(consume), dispatcher.index(transaction))
        self.assertLess(dispatcher.index(consume), dispatcher.index(reserve))
        self.assertIn("if ($idempotencyReserved)", dispatcher)

    def test_native_parent_enforces_signed_non_legacy_confirmation(self) -> None:
        config = (REPO_ROOT / "native-extensions/WorkCore/config/workcore-native.php").read_text(encoding="utf-8")
        provider = (REPO_ROOT / "native-extensions/WorkCore/System/WorkCoreServiceProvider.php").read_text(encoding="utf-8")
        for token in ("'approvals'", "'ttl_seconds'", "'enforce_all' => true", "'allow_legacy_human_confirmation' => false"):
            self.assertIn(token, config)
        for token in (
            "ConfirmationGrantSigner::class",
            "ConfirmationNonceStoreContract::class",
            "DatabaseConfirmationNonceStore::class",
            "ConfirmationGrantService::class",
            "BoundConfirmationVerifier::class",
            "ConfirmationVerifierContract::class",
        ):
            self.assertIn(token, provider)
        self.assertIn("true,", provider)
        self.assertIn("false,", provider)

    def test_action_controllers_issue_payload_bound_confirmation_grants(self) -> None:
        controllers = [
            REPO_ROOT / "native-extensions/WorkCore/System/Http/Controllers/ActionController.php",
            REPO_ROOT / "integration/host-overlay/app/Http/Controllers/Api/V1/WorkCore/ActionController.php",
        ]
        for path in controllers:
            content = path.read_text(encoding="utf-8")
            self.assertIn("public function confirm(", content)
            self.assertIn("ConfirmationGrantService", content)
            self.assertIn("payloadHash()", content)
            self.assertIn("$entitlements->allows", content)
            self.assertIn("$permissions->allows", content)
            self.assertIn("'confirmation_id' => $confirmationId", content)
            self.assertIn("'idempotency_key' => $idempotencyKey", content)
            self.assertIn("'max:2048'", content)

    def test_confirmation_routes_are_exposed_on_workcore_api(self) -> None:
        routes = [
            REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/Routes/api.php",
            REPO_ROOT / "integration/host-overlay/routes/api.php",
        ]
        for path in routes:
            content = path.read_text(encoding="utf-8")
            self.assertIn("actions/{action}/confirm", content)
            self.assertIn("'confirm'", content)


if __name__ == "__main__":
    unittest.main()
