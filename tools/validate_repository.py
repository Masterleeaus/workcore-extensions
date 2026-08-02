from __future__ import annotations

import argparse
import hashlib
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any

EXPECTED_PACKAGES = {
    'workcore-shared-foundation',
    'workcore-business-network',
    'workcore-commercial',
    'workcore-work-operations',
    'workcore-property-operations',
    'workcore-workforce-assurance',
}
DOMAIN_PACKAGES = EXPECTED_PACKAGES - {'workcore-shared-foundation'}
EXPECTED_ARCHITECTURE = 'five-domain-composition-with-shared-foundation'
EXPECTED_MODULE_COUNT = 35
EXPECTED_OWNED_FILE_COUNT = 2158


@dataclass(frozen=True)
class ValidationReport:
    errors: list[str]
    package_count: int
    module_count: int
    owned_file_count: int

    @property
    def valid(self) -> bool:
        return not self.errors


def _load_json(path: Path, errors: list[str]) -> dict[str, Any]:
    if not path.is_file():
        errors.append(f'missing JSON file: {path}')
        return {}
    try:
        value = json.loads(path.read_text(encoding='utf-8'))
    except (OSError, UnicodeDecodeError, json.JSONDecodeError) as error:
        errors.append(f'invalid JSON file {path}: {error}')
        return {}
    if not isinstance(value, dict):
        errors.append(f'JSON root must be an object: {path}')
        return {}
    return value


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open('rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            digest.update(chunk)
    return digest.hexdigest()


def _validate_checksums(package_root: Path, errors: list[str]) -> None:
    checksum_path = package_root / 'files.sha256.json'
    checksums = _load_json(checksum_path, errors)
    entries = checksums.get('files', [])
    declared_count = checksums.get('file_count')

    if not isinstance(entries, list):
        errors.append(f'{checksum_path}: files must be a list')
        return
    if declared_count != len(entries):
        errors.append(
            f'{checksum_path}: file_count {declared_count!r} does not match {len(entries)} entries'
        )

    declared_paths: set[str] = set()
    for index, entry in enumerate(entries):
        if not isinstance(entry, dict):
            errors.append(f'{checksum_path}: entry {index} must be an object')
            continue
        relative_path = entry.get('path')
        expected_sha = entry.get('sha256')
        expected_bytes = entry.get('bytes')
        if not isinstance(relative_path, str) or not relative_path:
            errors.append(f'{checksum_path}: entry {index} has an invalid path')
            continue
        if relative_path in declared_paths:
            errors.append(f'{checksum_path}: duplicate path {relative_path}')
            continue
        declared_paths.add(relative_path)

        file_path = package_root / relative_path
        if not file_path.is_file():
            errors.append(f'{package_root.name}: missing checksummed file {relative_path}')
            continue
        actual_bytes = file_path.stat().st_size
        if expected_bytes != actual_bytes:
            errors.append(
                f'{package_root.name}/{relative_path}: bytes {actual_bytes} != {expected_bytes}'
            )
        actual_sha = _sha256(file_path)
        if expected_sha != actual_sha:
            errors.append(
                f'{package_root.name}/{relative_path}: SHA-256 {actual_sha} != {expected_sha}'
            )

    actual_paths = {
        path.relative_to(package_root).as_posix()
        for path in package_root.rglob('*')
        if path.is_file() and path.name != 'files.sha256.json'
    }
    undeclared = sorted(actual_paths - declared_paths)
    missing_from_tree = sorted(declared_paths - actual_paths)
    for relative_path in undeclared:
        errors.append(f'{package_root.name}: file is not checksummed: {relative_path}')
    for relative_path in missing_from_tree:
        errors.append(f'{package_root.name}: checksum references absent file: {relative_path}')


def validate_repository(repo_root: Path) -> ValidationReport:
    repo_root = repo_root.resolve()
    errors: list[str] = []
    packages_root = repo_root / 'packages'

    if not packages_root.is_dir():
        return ValidationReport(['missing packages directory'], 0, 0, 0)

    actual_packages = {path.name for path in packages_root.iterdir() if path.is_dir()}
    missing_packages = sorted(EXPECTED_PACKAGES - actual_packages)
    unexpected_packages = sorted(actual_packages - EXPECTED_PACKAGES)
    for package in missing_packages:
        errors.append(f'missing package: {package}')
    for package in unexpected_packages:
        errors.append(f'unexpected package: {package}')

    ownership_path = repo_root / 'ownership-manifest.json'
    if not ownership_path.is_file():
        ownership_path = repo_root / 'dist' / 'ownership-manifest.json'
    ownership = _load_json(ownership_path, errors)

    architecture = ownership.get('architecture')
    if architecture != EXPECTED_ARCHITECTURE:
        errors.append(f'ownership architecture {architecture!r} != {EXPECTED_ARCHITECTURE!r}')

    owned_files = ownership.get('files', [])
    owned_file_count = len(owned_files) if isinstance(owned_files, list) else 0
    if ownership.get('file_count') != owned_file_count:
        errors.append('ownership file_count does not match files list length')
    if owned_file_count != EXPECTED_OWNED_FILE_COUNT:
        errors.append(f'owned file count {owned_file_count} != {EXPECTED_OWNED_FILE_COUNT}')

    groups = ownership.get('groups', {})
    if not isinstance(groups, dict):
        errors.append('ownership groups must be an object')
        groups = {}
    if len(groups) != 5:
        errors.append(f'ownership group count {len(groups)} != 5')

    module_owners: dict[str, str] = {}
    duplicate_modules: set[str] = set()
    for group_slug, group in groups.items():
        modules = group.get('modules', []) if isinstance(group, dict) else []
        if not isinstance(modules, list):
            errors.append(f'group {group_slug}: modules must be a list')
            continue
        for module in modules:
            if module in module_owners:
                duplicate_modules.add(str(module))
            else:
                module_owners[str(module)] = str(group_slug)
    for module in sorted(duplicate_modules):
        errors.append(f'module has multiple owners: {module}')

    module_count = len(module_owners)
    if ownership.get('module_count') != module_count:
        errors.append('ownership module_count does not match unique module ownership')
    if module_count != EXPECTED_MODULE_COUNT:
        errors.append(f'module count {module_count} != {EXPECTED_MODULE_COUNT}')

    rules = ownership.get('rules', {})
    expected_rules = {
        'automatic_fallback_loading': False,
        'canonical_namespace_preserved': True,
        'destructive_uninstall': False,
        'historical_migrations_owner': 'shared-foundation',
    }
    if rules != expected_rules:
        errors.append(f'ownership rules differ from required safety rules: {rules!r}')

    for package_name in sorted(actual_packages & EXPECTED_PACKAGES):
        package_root = packages_root / package_name
        composer = _load_json(package_root / 'composer.json', errors)
        expected_composer_name = package_name.replace('workcore-', 'workcore/', 1)
        if composer.get('name') != expected_composer_name:
            errors.append(
                f'{package_name}: composer name {composer.get("name")!r} != {expected_composer_name!r}'
            )
        require = composer.get('require', {})
        if package_name in DOMAIN_PACKAGES:
            if not isinstance(require, dict) or require.get('workcore/shared-foundation') != 'self.version':
                errors.append(f'{package_name}: Composer must require workcore/shared-foundation self.version')

            extension = _load_json(package_root / 'extension.json', errors)
            group_slug = package_name.removeprefix('workcore-')
            group = groups.get(group_slug, {}) if isinstance(groups, dict) else {}
            if extension.get('slug') != group_slug:
                errors.append(f'{package_name}: extension slug mismatch')
            if extension.get('requires') != ['workcore/shared-foundation']:
                errors.append(f'{package_name}: extension requires mismatch')
            if extension.get('modules') != group.get('modules'):
                errors.append(f'{package_name}: extension modules differ from ownership manifest')
            if extension.get('runtime_keys') != group.get('runtime_keys'):
                errors.append(f'{package_name}: extension runtime keys differ from ownership manifest')
            if extension.get('canonical_namespace_preserved') is not True:
                errors.append(f'{package_name}: canonical namespace preservation must be true')
            if extension.get('destructive_uninstall') is not False:
                errors.append(f'{package_name}: destructive uninstall must be false')
        elif (package_root / 'extension.json').exists():
            errors.append('workcore-shared-foundation must not masquerade as an optional extension')

        _validate_checksums(package_root, errors)

    return ValidationReport(
        errors=errors,
        package_count=len(actual_packages),
        module_count=module_count,
        owned_file_count=owned_file_count,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description='Validate the extracted WorkCore extension repository.')
    parser.add_argument('--repo', type=Path, default=Path.cwd())
    args = parser.parse_args()

    report = validate_repository(args.repo)
    print(
        f'packages={report.package_count} modules={report.module_count} '
        f'owned_files={report.owned_file_count} errors={len(report.errors)}'
    )
    for error in report.errors:
        print(f'ERROR: {error}')
    return 0 if report.valid else 1


if __name__ == '__main__':
    raise SystemExit(main())
