from __future__ import annotations

import unittest
from pathlib import Path

REPOSITORY_ROOT = Path(__file__).resolve().parents[1]


class RuntimeVerifierContractTests(unittest.TestCase):
    def test_runtime_verifier_checks_modules_bindings_routes_and_disabled_state(self) -> None:
        verifier = (REPOSITORY_ROOT / 'tools/verify_host_runtime.php').read_text(encoding='utf-8')
        required_tokens = [
            'WorkModuleRegistry::class',
            'BusinessActionRegistry::class',
            'ReadModelRegistry::class',
            'CapabilityRegistry::class',
            'api.workcore.actions.index',
            'workcore.tenant',
            'workcore.capability',
            'workcore.api',
            'expectedModulesForProfile',
            'WORKCORE_ENABLED',
        ]
        for token in required_tokens:
            self.assertIn(token, verifier)

    def test_host_matrix_executes_runtime_verifier_enabled_and_disabled(self) -> None:
        workflow = (REPOSITORY_ROOT / '.github/workflows/host-compatibility.yml').read_text(encoding='utf-8')
        self.assertIn('tools/verify_host_runtime.php', workflow)
        self.assertIn('--enabled=true', workflow)
        self.assertIn('--enabled=false', workflow)
        self.assertIn('--profile=', workflow)


if __name__ == '__main__':
    unittest.main()
