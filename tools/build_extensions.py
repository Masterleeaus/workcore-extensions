from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import zipfile
from collections import defaultdict
from pathlib import Path
from typing import Any, Iterable

PACKAGE_VERSION = '0.1.1'
SOURCE_RELEASE = 'WorkCore-MagicAI-Final-Consolidated-2026-08-02'
SOURCE_ARCHIVE_SHA256 = '6d735fc6716bb08bd0ab7fdab74d390a6a009318f75ecb3f1a9b663c8cdba327'
SOURCE_ARCHIVE_BYTES = 4021361

GROUPS: dict[str, dict[str, Any]] = {
    'business-network': {
        'title': 'WorkCore Business Network',
        'modules': [
            'CRM', 'Catalogue', 'Support', 'Knowledge', 'KnowledgeBase',
            'Reviews', 'Territories', 'Feedback', 'Wizards',
        ],
        'runtime_keys': [
            'crm', 'catalogue', 'support', 'knowledge', 'reviews',
            'territories', 'intelligence', 'expansion', 'wizards', 'ai',
        ],
        'system_slices': ['System/Intelligence', 'System/Expansion', 'System/AI'],
    },
    'commercial': {
        'title': 'WorkCore Commercial',
        'modules': ['Finance', 'Payroll', 'Inventory', 'Supply', 'TitanVault', 'TrustAccounting'],
        'runtime_keys': ['finance', 'payroll', 'inventory', 'supply', 'vault', 'trust_accounting'],
        'system_slices': [],
    },
    'work-operations': {
        'title': 'WorkCore Work Operations',
        'modules': ['Operations', 'Scheduling', 'Dispatch', 'RecurringServices', 'Forms', 'Repairs', 'Fleet', 'QRCode'],
        'runtime_keys': ['operations', 'scheduling', 'dispatch', 'recurring', 'forms', 'repairs', 'fleet'],
        'system_slices': [],
    },
    'property-operations': {
        'title': 'WorkCore Property Operations',
        'modules': ['Premises', 'Assets', 'Documents'],
        'runtime_keys': ['premises', 'assets', 'documents', 'vertical_operations'],
        'system_slices': ['System/Verticals', 'Verticals'],
    },
    'workforce-assurance': {
        'title': 'WorkCore Workforce Assurance',
        'modules': [
            'Workforce', 'People', 'AttendanceVerification', 'Rosters',
            'Attendance', 'Compliance', 'Assurance', 'Credentials', 'NDIS',
        ],
        'runtime_keys': [
            'workforce', 'people', 'attendance_verification', 'rosters',
            'attendance', 'compliance', 'assurance',
        ],
        'system_slices': [],
    },
}

OPTIONAL_INTEGRATIONS: dict[str, dict[str, str]] = {
    'business-network': {
        'workcore/work-operations': 'Enables support-ticket conversion into governed Work Orders.',
    },
    'commercial': {
        'workcore/business-network': 'Connects customer, receivable and CRM context to financial workflows.',
        'workcore/work-operations': 'Enables completed-job invoicing and job profitability workflows.',
    },
    'work-operations': {
        'workcore/business-network': 'Connects jobs with customers, services and support flows.',
        'workcore/property-operations': 'Enables premises, asset and trade-compliance context for field work.',
    },
    'property-operations': {
        'workcore/work-operations': 'Enables property jobs, permits, callbacks and compliance workflows.',
        'workcore/workforce-assurance': 'Enables NDIS, worker and assurance-backed vertical operations.',
    },
    'workforce-assurance': {
        'workcore/work-operations': 'Connects rosters, attendance and compliance to scheduled field work.',
        'workcore/property-operations': 'Enables premises evidence, NDIS accommodation and property assurance.',
    },
}

GROUP_PROVIDER_FILES = {
    'business-network': 'BusinessNetworkServiceProvider.php',
    'commercial': 'CommercialServiceProvider.php',
    'work-operations': 'WorkOperationsServiceProvider.php',
    'property-operations': 'PropertyOperationsServiceProvider.php',
    'workforce-assurance': 'WorkforceAssuranceServiceProvider.php',
}

GROUP_PROVIDER_CLASSES = {
    'business-network': 'BusinessNetworkServiceProvider',
    'commercial': 'CommercialServiceProvider',
    'work-operations': 'WorkOperationsServiceProvider',
    'property-operations': 'PropertyOperationsServiceProvider',
    'workforce-assurance': 'WorkforceAssuranceServiceProvider',
}

GROUP_MANIFEST_FILES = {
    'business-network': 'BusinessNetwork.json',
    'commercial': 'Commercial.json',
    'work-operations': 'WorkOperations.json',
    'property-operations': 'PropertyOperations.json',
    'workforce-assurance': 'WorkforceAssurance.json',
}


OPTIONAL_MEETUP_TENANCY_MIGRATION = r"""<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'active_company_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('active_company_id')->nullable()->after('id')->constrained('tz_companies')->nullOnDelete();
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'company_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
                $table->string('visibility')->default('company');
                $table->softDeletes();
                $table->index(['company_id', 'type']);
            });
        }

        if (Schema::hasTable('participants') && ! Schema::hasColumn('participants', 'company_id')) {
            Schema::table('participants', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('tz_companies')->cascadeOnDelete();
                $table->index(['company_id', 'user_id']);
            });
        }

        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'company_id')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('tz_companies')->cascadeOnDelete();
                $table->uuid('public_id')->nullable()->unique()->after('company_id');
                $table->char('attachment_sha256', 64)->nullable();
                $table->string('attachment_disk')->nullable();
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->index(['company_id', 'conversation_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'company_id')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropSoftDeletes();
                $table->dropColumn(['company_id', 'public_id', 'attachment_sha256', 'attachment_disk', 'metadata']);
            });
        }

        if (Schema::hasTable('participants') && Schema::hasColumn('participants', 'company_id')) {
            Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('company_id'));
        }

        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'company_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropSoftDeletes();
                $table->dropColumn(['company_id', 'created_by', 'visibility']);
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'active_company_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('active_company_id'));
        }
    }
};
"""


def discover_modules(source_root: Path) -> set[str]:
    modules_root = source_root / 'app/Domains/WorkCore/System/Modules'
    return {path.name for path in modules_root.iterdir() if path.is_dir()}


def validate_ownership(source_root: Path) -> dict[str, Any]:
    discovered = discover_modules(source_root)
    owners: dict[str, list[str]] = defaultdict(list)
    for group, definition in GROUPS.items():
        for module in definition['modules']:
            owners[module].append(group)

    missing = sorted(discovered - set(owners))
    unknown = sorted(set(owners) - discovered)
    duplicates = {module: groups for module, groups in sorted(owners.items()) if len(groups) > 1}

    if unknown:
        raise ValueError(f'Unknown module assignments: {unknown}')

    return {
        'missing': missing,
        'duplicates': duplicates,
        'assigned_count': len(owners),
        'discovered_count': len(discovered),
        'owners': dict(owners),
    }


def _copy_tree(source: Path, destination: Path) -> None:
    if not source.exists():
        raise FileNotFoundError(source)
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copytree(source, destination, dirs_exist_ok=True)


def _write_json(path: Path, payload: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2, sort_keys=True) + '\n', encoding='utf-8')


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def _files(root: Path) -> Iterable[Path]:
    return (path for path in sorted(root.rglob('*')) if path.is_file())


def _write_package_metadata(package_root: Path, package_name: str, title: str, package_type: str) -> None:
    composer: dict[str, Any] = {
        'name': f'workcore/{package_name}',
        'version': PACKAGE_VERSION,
        'description': title,
        'type': 'library',
        'license': 'proprietary',
        'autoload': {'classmap': ['src/']},
        'require': {'php': '^8.2'},
        'extra': {
            'workcore': {
                'package_type': package_type,
                'phase': 'canonical-namespace-extraction',
                'destructive_uninstall': False,
                'source_release': SOURCE_RELEASE,
                'source_archive_sha256': SOURCE_ARCHIVE_SHA256,
            },
        },
    }
    if package_type == 'shared-foundation':
        composer['require']['laravel/framework'] = '^10.0 || ^11.0 || ^12.0'
        composer['extra']['laravel'] = {
            'providers': ['App\\Domains\\WorkCore\\WorkCoreServiceProvider'],
        }
    else:
        composer['require']['workcore/shared-foundation'] = 'self.version'
        composer['suggest'] = OPTIONAL_INTEGRATIONS.get(package_name, {})
    _write_json(package_root / 'composer.json', composer)
    (package_root / 'README.md').write_text(
        f'# {title}\n\n'
        'Generated from the verified consolidated WorkCore source. Canonical PHP namespaces are preserved.\n\n'
        'Historical schema migrations remain in `workcore/shared-foundation` during this extraction phase.\n',
        encoding='utf-8',
    )


def _build_shared(source_root: Path, packages_root: Path) -> Path:
    package_root = packages_root / 'workcore-shared-foundation'
    source_workcore = source_root / 'app/Domains/WorkCore'
    destination_workcore = package_root / 'src/Domains/WorkCore'
    _copy_tree(source_workcore, destination_workcore)

    for definition in GROUPS.values():
        for module in definition['modules']:
            shutil.rmtree(destination_workcore / 'System/Modules' / module, ignore_errors=True)
        for relative_slice in definition['system_slices']:
            path = destination_workcore / relative_slice
            if path.is_dir():
                shutil.rmtree(path)
            elif path.exists():
                path.unlink()

    for filename in GROUP_PROVIDER_FILES.values():
        path = destination_workcore / 'Providers' / filename
        if path.exists():
            path.unlink()
    for filename in GROUP_MANIFEST_FILES.values():
        path = destination_workcore / 'Manifests' / filename
        if path.exists():
            path.unlink()

    _write_package_metadata(
        package_root,
        'shared-foundation',
        'WorkCore Shared Foundation',
        'shared-foundation',
    )
    _patch_shared_provider(package_root)
    _patch_shared_config(package_root)
    _patch_shared_migrations(package_root)
    return package_root


def _build_group(source_root: Path, packages_root: Path, group: str, definition: dict[str, Any]) -> Path:
    package_root = packages_root / f'workcore-{group}'
    source_workcore = source_root / 'app/Domains/WorkCore'
    destination_workcore = package_root / 'src/Domains/WorkCore'

    for module in definition['modules']:
        _copy_tree(
            source_workcore / 'System/Modules' / module,
            destination_workcore / 'System/Modules' / module,
        )
    for relative_slice in definition['system_slices']:
        _copy_tree(source_workcore / relative_slice, destination_workcore / relative_slice)

    provider_file = GROUP_PROVIDER_FILES[group]
    provider_destination = destination_workcore / 'Providers' / provider_file
    provider_destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source_workcore / 'Providers' / provider_file, provider_destination)

    original_manifest = source_workcore / 'Manifests' / GROUP_MANIFEST_FILES[group]
    if original_manifest.is_file():
        docs_manifest = package_root / 'docs/original-composition-manifest.json'
        docs_manifest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(original_manifest, docs_manifest)

    _write_package_metadata(package_root, group, definition['title'], 'domain-extension')
    _write_group_provider(package_root, group, definition)
    _write_group_manifest(package_root, group, definition)
    _write_json(
        package_root / 'extension.json',
        {
            'slug': group,
            'name': definition['title'],
            'modules': definition['modules'],
            'runtime_keys': definition['runtime_keys'],
            'system_slices': definition['system_slices'],
            'requires': ['workcore/shared-foundation'],
            'integrates_with': sorted(OPTIONAL_INTEGRATIONS.get(group, {})),
            'canonical_namespace_preserved': True,
            'destructive_uninstall': False,
        },
    )
    return package_root


def _patch_shared_provider(package_root: Path) -> None:
    provider_path = package_root / 'src/Domains/WorkCore/WorkCoreServiceProvider.php'
    source = provider_path.read_text(encoding='utf-8')
    source = source.replace(
        'use App\\Domains\\WorkCore\\Providers\\{BusinessNetworkServiceProvider, CommercialServiceProvider, PropertyOperationsServiceProvider, WorkforceAssuranceServiceProvider, WorkOperationsServiceProvider};\n',
        '',
    )
    helpers_marker = "        $helpers = __DIR__ . '/System/Support/helpers.php';"
    if helpers_marker not in source:
        raise RuntimeError('Unable to locate the WorkCore helper registration marker.')
    source = source.replace(
        helpers_marker,
        "        if (! (bool) config('workcore.enabled', true)) {\n"
        "            return;\n"
        "        }\n\n"
        + helpers_marker,
        1,
    )
    start = source.index('    private function registerModules(): void\n')
    end = source.index('    private function registerRateLimiters(): void\n', start)
    group_provider_lines = '\n'.join(
        f"            \\App\\Domains\\WorkCore\\Providers\\{GROUP_PROVIDER_CLASSES[group]}::class,"
        for group in GROUPS
    )
    replacement = f'''    private function registerModules(): void
    {{
        $registry = $this->app->make(WorkModuleRegistry::class);
        foreach ((array) config('workcore.modules', []) as $key => $module) {{
            if (($module['enabled'] ?? false) !== true) {{
                continue;
            }}

            $provider = $module['provider'] ?? null;
            if (! is_string($provider)) {{
                throw new RuntimeException("Configured WorkCore module [{{$key}}] has no provider class.");
            }}

            if (! class_exists($provider)) {{
                continue;
            }}

            $registry->define((string) $key, $provider);
        }}

        foreach ([
{group_provider_lines}
        ] as $provider) {{
            if (! class_exists($provider)) {{
                continue;
            }}

            $this->app->register($provider);
        }}
    }}

'''
    provider_path.write_text(source[:start] + replacement + source[end:], encoding='utf-8')



def _patch_shared_config(package_root: Path) -> None:
    config_path = package_root / 'src/Domains/WorkCore/Config/workcore.php'
    source = config_path.read_text(encoding='utf-8')
    old = """$financeConfig = require __DIR__ . '/../System/Modules/Finance/config/titan-money.php';
$financePermissions = require __DIR__ . '/../System/Modules/Finance/config/permissions.php';"""
    new = """$financeConfigPath = __DIR__ . '/../System/Modules/Finance/config/titan-money.php';
$financePermissionsPath = __DIR__ . '/../System/Modules/Finance/config/permissions.php';
$financeConfig = is_file($financeConfigPath) ? require $financeConfigPath : [];
$financePermissions = is_file($financePermissionsPath) ? require $financePermissionsPath : [];"""
    if old not in source:
        raise RuntimeError('Unable to locate the WorkCore Finance config imports for optional-package patching.')
    config_path.write_text(source.replace(old, new, 1), encoding='utf-8')


def _patch_shared_migrations(package_root: Path) -> None:
    migration_path = (
        package_root / 'src/Domains/WorkCore/Database/Migrations/'
        '2026_07_23_120058_create_tz_ai_knowledge_tables.php'
    )
    source = migration_path.read_text(encoding='utf-8')
    create_marker = """    public function up(): void
    {
        Schema::create('tz_ai_knowledge_documents', function (Blueprint $table): void {"""
    create_replacement = """    public function up(): void
    {
        $supportsFullText = in_array(
            Schema::getConnection()->getDriverName(),
            ['mysql', 'mariadb', 'pgsql'],
            true,
        );

        Schema::create('tz_ai_knowledge_documents', function (Blueprint $table): void {"""
    chunk_marker = "Schema::create('tz_ai_knowledge_chunks', function (Blueprint $table): void {"
    chunk_replacement = "Schema::create('tz_ai_knowledge_chunks', function (Blueprint $table) use ($supportsFullText): void {"
    index_marker = "            $table->fullText('content', 'ai_kchunk_content_ft');"
    index_replacement = """            if ($supportsFullText) {
                $table->fullText('content', 'ai_kchunk_content_ft');
            }"""

    if create_marker not in source or chunk_marker not in source or index_marker not in source:
        raise RuntimeError('Unable to locate the AI knowledge full-text migration markers.')

    source = source.replace(create_marker, create_replacement, 1)
    source = source.replace(chunk_marker, chunk_replacement, 1)
    source = source.replace(index_marker, index_replacement, 1)
    migration_path.write_text(source, encoding='utf-8')

def _write_group_provider(package_root: Path, group: str, definition: dict[str, Any]) -> None:
    class_name = GROUP_PROVIDER_CLASSES[group]
    runtime_lines = ',\n'.join(f"        '{key}'" for key in definition['runtime_keys'])
    provider = f'''<?php

declare(strict_types=1);

namespace App\\Domains\\WorkCore\\Providers;

use App\\Domains\\WorkCore\\System\\Registry\\WorkModuleRegistry;
use Illuminate\\Support\\ServiceProvider;

final class {class_name} extends ServiceProvider
{{
    private const MODULES = [
{runtime_lines},
    ];

    public function register(): void
    {{
        $registry = $this->app->make(WorkModuleRegistry::class);
        foreach (self::MODULES as $module) {{
            if ($registry->has($module)) {{
                $registry->load($module);
            }}
        }}
    }}
}}
'''
    path = package_root / 'src/Domains/WorkCore/Providers' / GROUP_PROVIDER_FILES[group]
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(provider, encoding='utf-8')


def _write_group_manifest(package_root: Path, group: str, definition: dict[str, Any]) -> None:
    manifest_path = package_root / 'src/Domains/WorkCore/Manifests' / GROUP_MANIFEST_FILES[group]
    _write_json(
        manifest_path,
        {
            'architecture': 'installable-domain-extension',
            'domain': GROUP_PROVIDER_CLASSES[group].removesuffix('ServiceProvider'),
            'package': f'workcore/{group}',
            'provider': f'App\\Domains\\WorkCore\\Providers\\{GROUP_PROVIDER_CLASSES[group]}',
            'modules': definition['modules'],
            'runtime_keys': definition['runtime_keys'],
            'system_slices': definition['system_slices'],
            'dependencies': ['workcore/shared-foundation'],
            'status': 'phase-one canonical namespace extraction',
            'destructive_uninstall': False,
        },
    )


def _owner_for_relative(relative: Path) -> str:
    parts = relative.parts
    if len(parts) >= 3 and parts[0:2] == ('System', 'Modules'):
        module = parts[2]
        for group, definition in GROUPS.items():
            if module in definition['modules']:
                return group
    relative_posix = relative.as_posix()
    for group, definition in GROUPS.items():
        for slice_path in definition['system_slices']:
            if relative_posix == slice_path or relative_posix.startswith(f'{slice_path}/'):
                return group
        if relative_posix == f"Providers/{GROUP_PROVIDER_FILES[group]}":
            return group
        if relative_posix == f"Manifests/{GROUP_MANIFEST_FILES[group]}":
            return group
    return 'shared-foundation'


def _destination_for_owner(output_root: Path, owner: str, relative: Path) -> Path:
    package = 'workcore-shared-foundation' if owner == 'shared-foundation' else f'workcore-{owner}'
    return output_root / 'packages' / package / 'src/Domains/WorkCore' / relative


def _write_package_checksums(package_root: Path) -> None:
    entries = []
    for path in _files(package_root):
        if path.name == 'files.sha256.json':
            continue
        entries.append({'path': path.relative_to(package_root).as_posix(), 'sha256': _sha256(path), 'bytes': path.stat().st_size})
    _write_json(package_root / 'files.sha256.json', {'files': entries, 'file_count': len(entries)})


def _write_ownership_manifest(source_root: Path, output_root: Path) -> Path:
    source_workcore = source_root / 'app/Domains/WorkCore'
    files = []
    for source_file in _files(source_workcore):
        relative = source_file.relative_to(source_workcore)
        owner = _owner_for_relative(relative)
        destination = _destination_for_owner(output_root, owner, relative)
        if not destination.is_file():
            raise RuntimeError(f'Owned file was not emitted: {relative} -> {destination}')
        files.append({
            'source': f'app/Domains/WorkCore/{relative.as_posix()}',
            'owner': owner,
            'destination': destination.relative_to(output_root).as_posix(),
            'source_sha256': _sha256(source_file),
            'destination_sha256': _sha256(destination),
            'transformed': source_file.read_bytes() != destination.read_bytes(),
        })

    manifest = {
        'architecture': 'five-domain-composition-with-shared-foundation',
        'source_root': str(source_root),
        'source_release': SOURCE_RELEASE,
        'source_archive_sha256': SOURCE_ARCHIVE_SHA256,
        'source_archive_bytes': SOURCE_ARCHIVE_BYTES,
        'module_count': 35,
        'groups': GROUPS,
        'files': files,
        'file_count': len(files),
        'rules': {
            'canonical_namespace_preserved': True,
            'historical_migrations_owner': 'shared-foundation',
            'destructive_uninstall': False,
            'automatic_fallback_loading': False,
        },
    }
    path = output_root / 'dist/ownership-manifest.json'
    _write_json(path, manifest)
    return path


def _zip_entries(zip_path: Path, entries: Iterable[tuple[Path, str]]) -> None:
    zip_path.parent.mkdir(parents=True, exist_ok=True)
    if zip_path.exists():
        zip_path.unlink()
    with zipfile.ZipFile(zip_path, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for source, archive_name in sorted(entries, key=lambda item: item[1]):
            info = zipfile.ZipInfo(archive_name, date_time=(2026, 8, 2, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, source.read_bytes())


def _zip_directory(source_root: Path, zip_path: Path, prefix: str | None = None) -> None:
    prefix = prefix or source_root.name
    entries = ((path, f'{prefix}/{path.relative_to(source_root).as_posix()}') for path in _files(source_root))
    _zip_entries(zip_path, entries)


def _write_releases(output_root: Path, built: dict[str, Path], ownership_manifest: Path) -> None:
    dist = output_root / 'dist'
    dist.mkdir(parents=True, exist_ok=True)
    for package_root in built.values():
        _zip_directory(package_root, dist / f'{package_root.name}.zip')

    workspace_entries: list[tuple[Path, str]] = []
    for package_root in built.values():
        for path in _files(package_root):
            workspace_entries.append((path, f'workcore-extension-workspace/packages/{package_root.name}/{path.relative_to(package_root).as_posix()}'))
    workspace_entries.append((ownership_manifest, 'workcore-extension-workspace/ownership-manifest.json'))
    _zip_entries(dist / 'workcore-extension-workspace.zip', workspace_entries)


def _copy_host_overlay(source_root: Path, output_root: Path) -> None:
    overlay_root = output_root / 'integration/host-overlay'
    if overlay_root.exists():
        shutil.rmtree(overlay_root)
    for source_file in _files(source_root):
        relative = source_file.relative_to(source_root)
        if relative.parts[:3] == ('app', 'Domains', 'WorkCore'):
            continue
        if relative.parts and relative.parts[0] in {'storage'}:
            continue
        destination = overlay_root / relative
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source_file, destination)


def _patch_host_overlay(output_root: Path) -> None:
    providers_path = output_root / 'integration/host-overlay/bootstrap/providers.php'
    if not providers_path.is_file():
        raise RuntimeError('Host overlay bootstrap/providers.php was not emitted.')
    source = providers_path.read_text(encoding='utf-8')
    source = source.replace(
        "    App\\Domains\\WorkCore\\WorkCoreServiceProvider::class,\n",
        '',
    )
    providers_path.write_text(source, encoding='utf-8')
    _write_optional_meetup_tenancy_migration(output_root)


def _write_optional_meetup_tenancy_migration(output_root: Path) -> None:
    migration_path = (
        output_root / 'integration/host-overlay/database/migrations/'
        '2026_07_25_000001_add_workcore_tenancy_to_meetup.php'
    )
    if not migration_path.is_file():
        raise RuntimeError('Host overlay Meetup tenancy migration was not emitted.')

    migration_path.write_text(OPTIONAL_MEETUP_TENANCY_MIGRATION, encoding='utf-8')


def build(source_root: Path, output_root: Path, include_host_overlay: bool = False) -> dict[str, Path]:
    source_root = source_root.resolve()
    output_root = output_root.resolve()
    validation = validate_ownership(source_root)
    if validation['missing'] or validation['duplicates']:
        raise ValueError(f'Invalid ownership: {validation}')

    packages_root = output_root / 'packages'
    dist_root = output_root / 'dist'
    for path in (packages_root, dist_root):
        if path.exists():
            shutil.rmtree(path)
        path.mkdir(parents=True, exist_ok=True)

    built: dict[str, Path] = {'shared-foundation': _build_shared(source_root, packages_root)}
    for group, definition in GROUPS.items():
        built[group] = _build_group(source_root, packages_root, group, definition)

    for package_root in built.values():
        _write_package_checksums(package_root)

    ownership_manifest = _write_ownership_manifest(source_root, output_root)
    _write_releases(output_root, built, ownership_manifest)

    if include_host_overlay:
        _copy_host_overlay(source_root, output_root)
        _patch_host_overlay(output_root)
        archive_candidates = [
            source_root.parent / 'WorkCore-MagicAI-Final-Consolidated-2026-08-02(1).zip',
            source_root.parent / 'WorkCore-MagicAI-Final-Consolidated-2026-08-02.zip',
        ]
        for baseline_zip in archive_candidates:
            if baseline_zip.is_file():
                shutil.copy2(
                    baseline_zip,
                    output_root / 'dist' / 'WorkCore-MagicAI-Final-Consolidated-2026-08-02.zip',
                )
                break

        completion_manifest = source_root / 'MAGICAI-WORKCORE-FINAL-COMPLETION-MANIFEST.md'
        if completion_manifest.is_file():
            destination = output_root / 'docs/source/MAGICAI-WORKCORE-FINAL-COMPLETION-MANIFEST.md'
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(completion_manifest, destination)

    return built


def main() -> int:
    parser = argparse.ArgumentParser(description='Build five WorkCore domain extensions.')
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--include-host-overlay', action='store_true')
    args = parser.parse_args()
    built = build(args.source, args.output, include_host_overlay=args.include_host_overlay)
    print(json.dumps({key: str(value) for key, value in built.items()}, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
