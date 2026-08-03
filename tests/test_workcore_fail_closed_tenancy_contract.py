from __future__ import annotations

import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
CANONICAL_TRAIT = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Tenancy/BelongsToCompany.php"
PRIVILEGED_CONTRACT = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Contracts/PrivilegedTenantAccessContract.php"
PRIVILEGED_RUNTIME = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Tenancy/PrivilegedTenantAccess.php"
PRIVILEGED_MIGRATION = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/Database/Migrations/2026_08_03_000000_create_tz_privileged_tenant_access_audits_table.php"
MISSING_TENANT = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Tenancy/MissingTenantContextException.php"
API_EXCEPTION_MIDDLEWARE = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Api/Middleware/WorkCoreApiExceptionMiddleware.php"
PROVIDER = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/WorkCoreServiceProvider.php"
IDENTITY_ADAPTER = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Identity/MagicAIUserCompanyAdapter.php"
CREATE_COMPANY = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Company/Actions/CreateCompany.php"
ACCEPT_INVITATION = REPO_ROOT / "packages/workcore-shared-foundation/src/Domains/WorkCore/System/Company/Actions/AcceptCompanyInvitation.php"
PREMISES_TRAITS = [
    REPO_ROOT / "packages/workcore-property-operations/src/Domains/WorkCore/System/Modules/Premises/Entities/Traits/BelongsToCompany.php",
    REPO_ROOT / "packages/workcore-property-operations/src/Domains/WorkCore/System/Modules/Premises/Entities/Concerns/BelongsToCompany.php",
]


class WorkCoreFailClosedTenancyContractTests(unittest.TestCase):
    def test_canonical_trait_scopes_or_throws_unless_privileged(self) -> None:
        content = CANONICAL_TRAIT.read_text(encoding="utf-8")
        self.assertIn("PrivilegedTenantAccessContract", content)
        self.assertIn("MissingTenantContextException", content)
        self.assertIn("if ($context->hasTenant())", content)
        self.assertIn("if ($privileged->isActive())", content)
        self.assertIn("throw new MissingTenantContextException", content)
        self.assertLess(content.index("if ($context->hasTenant())"), content.index("if ($privileged->isActive())"))
        self.assertLess(content.index("if ($privileged->isActive())"), content.index("throw new MissingTenantContextException"))

    def test_canonical_trait_exposes_no_generic_scope_bypass(self) -> None:
        content = CANONICAL_TRAIT.read_text(encoding="utf-8")
        self.assertNotIn("queryForExplicitCompany", content)
        self.assertNotIn("withoutGlobalScope('workcore_company')", content)

    def test_membership_bootstrap_uses_a_narrow_trusted_scope_bypass(self) -> None:
        adapter = IDENTITY_ADAPTER.read_text(encoding="utf-8")
        self.assertIn("CompanyMember::withoutGlobalScope('workcore_company')", adapter)
        self.assertIn("->where('company_id', $companyId)", adapter)
        self.assertIn("->where('user_id', $userId)", adapter)
        self.assertIn("->where('status', 'active')", adapter)
        self.assertNotIn("queryForExplicitCompany", adapter)
        self.assertNotIn("CompanyMember::query()", adapter)

    def test_privileged_access_requires_actor_and_reason_and_is_durably_audited(self) -> None:
        contract = PRIVILEGED_CONTRACT.read_text(encoding="utf-8")
        runtime = PRIVILEGED_RUNTIME.read_text(encoding="utf-8")
        migration = PRIVILEGED_MIGRATION.read_text(encoding="utf-8")
        self.assertIn("public function isActive(): bool", contract)
        self.assertIn("public function run(int|string $actorId, string $reason, callable $callback): mixed", contract)
        self.assertIn("InvalidArgumentException", runtime)
        self.assertIn("tz_privileged_tenant_access_audits", runtime)
        self.assertIn("workcore.cross_company_access.started", runtime)
        self.assertIn("workcore.cross_company_access.completed", runtime)
        self.assertIn("workcore.cross_company_access.failed", runtime)
        self.assertIn("finally", runtime)
        self.assertIn("$this->depth--", runtime)
        self.assertIn("Schema::create('tz_privileged_tenant_access_audits'", migration)

    def test_provider_registers_scoped_privileged_context(self) -> None:
        provider = PROVIDER.read_text(encoding="utf-8")
        self.assertIn("PrivilegedTenantAccessContract", provider)
        self.assertIn("PrivilegedTenantAccess", provider)
        self.assertIn("$this->app->scoped(PrivilegedTenantAccessContract::class", provider)

    def test_pre_tenant_company_workflows_are_explicitly_privileged(self) -> None:
        for path in (CREATE_COMPANY, ACCEPT_INVITATION):
            content = path.read_text(encoding="utf-8")
            self.assertIn("PrivilegedTenantAccessContract", content)
            self.assertIn("$this->privileged->run(", content)
            self.assertIn("DB::transaction", content)

    def test_all_premises_traits_delegate_to_canonical_tenant_boundary(self) -> None:
        canonical_use = "use \\App\\Domains\\WorkCore\\System\\Tenancy\\BelongsToCompany;"
        for path in PREMISES_TRAITS:
            content = path.read_text(encoding="utf-8")
            self.assertIn(canonical_use, content)
            self.assertNotIn("static::addGlobalScope", content)
            self.assertNotIn("function_exists('company')", content)

    def test_missing_tenant_exception_is_explicit_and_api_safe(self) -> None:
        exception = MISSING_TENANT.read_text(encoding="utf-8")
        middleware = API_EXCEPTION_MIDDLEWARE.read_text(encoding="utf-8")
        self.assertIn("final class MissingTenantContextException extends RuntimeException", exception)
        self.assertIn("catch (MissingTenantContextException", middleware)
        self.assertIn("'tenant_required'", middleware)
        self.assertIn(", 409)", middleware)
        self.assertLess(middleware.index("catch (MissingTenantContextException"), middleware.index("catch (Throwable"))


if __name__ == "__main__":
    unittest.main()
