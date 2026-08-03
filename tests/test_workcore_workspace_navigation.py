from __future__ import annotations

import re
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[1]
NATIVE = REPO_ROOT / "native-extensions/WorkCore"
CATALOGUE = NATIVE / "System/Navigation/WorkCoreWorkspaceCatalogue.php"
WORKSPACE_CONFIG = NATIVE / "config/workcore-workspaces.php"
MANIFEST = NATIVE / "System/Navigation/WorkCoreWorkspaceManifest.php"
MENU_SYNC = NATIVE / "System/Navigation/MagicAIMenuSynchronizer.php"
WORKSPACE_CONTROLLER = NATIVE / "System/Http/Controllers/WorkspaceController.php"
MANIFEST_CONTROLLER = NATIVE / "System/Http/Controllers/WorkspaceManifestController.php"
MENU_COMMAND = NATIVE / "System/Console/Commands/SyncWorkCoreMenusCommand.php"
USER_ROUTES = NATIVE / "routes/user.php"
API_ROUTES = NATIVE / "routes/api.php"
WORKSPACE_VIEW = NATIVE / "resources/views/workspace.blade.php"
PROVIDER = NATIVE / "System/WorkCoreServiceProvider.php"
MENU_MIGRATION = NATIVE / "database/migrations/2026_08_04_010000_sync_workcore_magicai_menus.php"
FIXTURE_VERIFIER = REPO_ROOT / "tools/verify_workcore_workspace_navigation.php"
FIXTURE_WORKFLOW = REPO_ROOT / ".github/workflows/magicai-native-laravel10.yml"


class WorkCoreWorkspaceCatalogueTests(unittest.TestCase):
    def test_catalogue_defines_five_ordered_workspace_roots(self) -> None:
        config = WORKSPACE_CONFIG.read_text(encoding="utf-8")
        positions = [config.index(f"'{key}' => $workspace(") for key in (
            "crm",
            "operations",
            "workforce",
            "resources",
            "commercial",
        )]
        self.assertEqual(sorted(positions), positions)
        for token in (
            "$workspace = static fn",
            "$section = static fn",
            "string $menuKey",
            "string $routeName",
            "string $path",
            "int $order",
            "array $capabilities",
            "array $sections",
        ):
            self.assertIn(token, config)

    def test_catalogue_validates_and_flattens_definitions(self) -> None:
        content = CATALOGUE.read_text(encoding="utf-8")
        for token in (
            "final class WorkCoreWorkspaceCatalogue",
            "public function all(): array",
            "public function workspace(string $key): ?array",
            "public function section(string $workspace, string $section): ?array",
            "public function menuDefinitions(): array",
            "Duplicate WorkCore workspace menu key",
            "Duplicate WorkCore workspace route name",
            "InvalidArgumentException",
            "uasort",
        ):
            self.assertIn(token, content)

    def test_workspace_keys_routes_and_paths_are_unique(self) -> None:
        config = WORKSPACE_CONFIG.read_text(encoding="utf-8")
        definitions = re.findall(
            r"\$(?:workspace|section)\(\s*'([^']+)'\s*,\s*'[^']+'\s*,\s*'[^']+'\s*,\s*'([^']+)'\s*,\s*'([^']+)'",
            config,
            re.S,
        )
        self.assertEqual(42, len(definitions))
        menu_keys = [definition[0] for definition in definitions]
        route_names = [definition[1] for definition in definitions]
        paths = [definition[2] for definition in definitions]
        self.assertEqual(len(menu_keys), len(set(menu_keys)))
        self.assertEqual(len(route_names), len(set(route_names)))
        self.assertEqual(len(paths), len(set(paths)))
        self.assertTrue(all(key.startswith("workcore_") for key in menu_keys))
        self.assertTrue(all(route.startswith("dashboard.user.workcore.") for route in route_names))


class WorkCoreWorkspaceManifestTests(unittest.TestCase):
    def test_manifest_filters_by_registered_and_entitled_capabilities(self) -> None:
        content = MANIFEST.read_text(encoding="utf-8")
        for token in (
            "CapabilityRegistry",
            "EntitlementResolverContract",
            "EntitlementRevisionResolverContract",
            "TenantContextContract",
            "public function forActiveCompany(): array",
            "public function forCompany(int $companyId): array",
            "public function findForCompany(int $companyId, string $workspace): ?array",
            "$this->capabilities->has($capability)",
            "$this->entitlements->allows($companyId, $capability)",
            "'entitlement_revision'",
            "'company_id'",
            "'workspaces'",
        ):
            self.assertIn(token, content)

    def test_native_api_exposes_workspace_manifest(self) -> None:
        routes = API_ROUTES.read_text(encoding="utf-8")
        controller = MANIFEST_CONTROLLER.read_text(encoding="utf-8")
        self.assertIn("Route::get('workspaces'", routes)
        self.assertIn("Route::get('workspaces/{workspace}'", routes)
        self.assertIn("api.workcore.workspaces.index", routes)
        self.assertIn("api.workcore.workspaces.show", routes)
        self.assertIn("WorkspaceManifestController", routes)
        self.assertIn("response()->json", controller)
        self.assertIn("abort(404", controller)


class WorkCoreWorkspaceWebShellTests(unittest.TestCase):
    def test_provider_loads_routes_views_translations_and_navigation_services(self) -> None:
        content = PROVIDER.read_text(encoding="utf-8")
        for token in (
            "WorkCoreWorkspaceCatalogue::class",
            "WorkCoreWorkspaceManifest::class",
            "MagicAIMenuSynchronizer::class",
            "SyncWorkCoreMenusCommand::class",
            "loadViewsFrom(__DIR__ . '/../resources/views', 'workcore')",
            "loadTranslationsFrom(__DIR__ . '/../resources/lang', 'workcore')",
            "registerWorkspaceRoutes",
            "routes/user.php",
            "routes/api.php",
        ):
            self.assertIn(token, content)

    def test_user_routes_are_catalogue_driven_and_capability_gated(self) -> None:
        content = USER_ROUTES.read_text(encoding="utf-8")
        for token in (
            "WorkCoreWorkspaceCatalogue",
            "WorkspaceController",
            "workcore.tenant",
            "workcore.capability:",
            "foreach ($catalogue->all() as $workspaceKey => $workspace)",
            "foreach ($workspace['sections'] as $sectionKey => $section)",
            "->name($workspace['route_name'])",
            "->name($section['route_name'])",
        ):
            self.assertIn(token, content)

    def test_controller_resolves_only_catalogue_definitions(self) -> None:
        content = WORKSPACE_CONTROLLER.read_text(encoding="utf-8")
        self.assertIn("$this->catalogue->workspace($workspaceKey)", content)
        self.assertIn("$this->catalogue->section($workspaceKey, (string) $sectionKey)", content)
        self.assertIn("$this->manifest->forActiveCompany()", content)
        self.assertIn("abort(404", content)
        self.assertIn("return view('workcore::workspace'", content)
        self.assertNotIn("view($workspace", content)
        self.assertNotIn("view($section", content)

    def test_workspace_view_uses_magicai_layout_without_legacy_assets(self) -> None:
        content = WORKSPACE_VIEW.read_text(encoding="utf-8")
        self.assertIn("<x-layouts.app>", content)
        self.assertIn("data-workcore-workspace", content)
        self.assertIn("route($section['route_name'])", content)
        self.assertIn("aria-current", content)
        for legacy in ("bootstrap", "jquery", "select2", "DataTable"):
            self.assertNotIn(legacy, content)


class MagicAIMenuSynchronizerTests(unittest.TestCase):
    def test_synchronizer_is_idempotent_and_preserves_admin_fields(self) -> None:
        content = MENU_SYNC.read_text(encoding="utf-8")
        for token in (
            "final class MagicAIMenuSynchronizer",
            "public function sync(): array",
            "Schema::hasTable('menus')",
            "Schema::hasColumn('menus'",
            "ConnectionInterface",
            "WorkCoreWorkspaceCatalogue",
            "preserved",
            "where('key', 'like', 'workcore_%')",
            "whereNotIn('key', $ownedKeys)",
            "'created'",
            "'updated'",
            "'unchanged'",
            "'disabled'",
            "MenuService",
            "regenerate",
        ):
            self.assertIn(token, content)
        self.assertNotIn("->delete()", content)

    def test_command_and_migration_use_the_same_synchronizer(self) -> None:
        command = MENU_COMMAND.read_text(encoding="utf-8")
        migration = MENU_MIGRATION.read_text(encoding="utf-8")
        self.assertIn("workcore:sync-menus", command)
        self.assertIn("MagicAIMenuSynchronizer", command)
        self.assertIn("$this->synchronizer->sync()", command)
        self.assertIn("MagicAIMenuSynchronizer", migration)
        self.assertIn("Schema::hasTable('menus')", migration)
        self.assertIn("->update(['is_active' => false", migration)
        self.assertNotIn("dropIfExists", migration)


class WorkCoreWorkspaceLaravelFixtureTests(unittest.TestCase):
    def test_real_laravel_fixture_runs_workspace_navigation_verifier(self) -> None:
        workflow = FIXTURE_WORKFLOW.read_text(encoding="utf-8")
        self.assertIn("verify_workcore_workspace_navigation.php", workflow)
        self.assertIn("Run database-backed WorkCore workspace navigation fixture", workflow)
        self.assertIn("matrix.profile == 'full'", workflow)

    def test_verifier_proves_menu_sync_routes_manifest_and_expiry(self) -> None:
        content = FIXTURE_VERIFIER.read_text(encoding="utf-8")
        for token in (
            "Schema::create('menus'",
            "MagicAIMenuSynchronizer::class",
            "WorkCoreWorkspaceManifest::class",
            "TenantContextContract::class",
            "workcore_custom_retired",
            "workcore_commercial_dropdown",
            "dashboard.user.workcore.crm.index",
            "dashboard.user.workcore.operations.index",
            "dashboard.user.workcore.workforce.index",
            "dashboard.user.workcore.resources.index",
            "dashboard.user.workcore.commercial.index",
            "first_sync",
            "second_sync",
            "commercial_before_expiry",
            "commercial_after_expiry",
        ):
            self.assertIn(token, content)
        self.assertIn("$tenant->set($companyId, $userId)", content)
        self.assertIn("$menus->sync()", content)
        self.assertIn("$manifest->forActiveCompany()", content)
        self.assertIn("RuntimeException", content)


if __name__ == "__main__":
    unittest.main()
