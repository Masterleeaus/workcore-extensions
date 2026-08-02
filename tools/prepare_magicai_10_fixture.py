#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import json
import os
import re
import shutil
from pathlib import Path

MODULE_FLAGS = [
    'CRM', 'CATALOGUE', 'SUPPORT', 'KNOWLEDGE', 'REVIEWS', 'TERRITORIES',
    'INTELLIGENCE', 'EXPANSION', 'WIZARDS', 'NATIVE_AI', 'FINANCE', 'PAYROLL',
    'INVENTORY', 'SUPPLY', 'VAULT', 'TRUST_ACCOUNTING', 'OPERATIONS',
    'SCHEDULING', 'DISPATCH', 'RECURRING', 'FORMS', 'REPAIRS', 'FLEET',
    'PREMISES', 'ASSETS', 'DOCUMENTS', 'VERTICAL_OPERATIONS', 'WORKFORCE',
    'PEOPLE', 'ATTENDANCE_VERIFICATION', 'ROSTERS', 'ATTENDANCE',
    'COMPLIANCE', 'ASSURANCE',
]


def _copy_tree(source: Path, destination: Path) -> None:
    for path in source.rglob('*'):
        relative = path.relative_to(source)
        target = destination / relative
        if path.is_dir():
            target.mkdir(parents=True, exist_ok=True)
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(path, target)


def _set_env(path: Path, values: dict[str, str]) -> None:
    existing: list[str] = []
    if path.is_file():
        for line in path.read_text(encoding='utf-8').splitlines():
            key = line.split('=', 1)[0] if '=' in line else ''
            if key not in values:
                existing.append(line)
    existing.extend(f'{key}={value}' for key, value in values.items())
    path.write_text('\n'.join(existing) + '\n', encoding='utf-8')


def prepare_fixture(repo: Path, build: Path, host: Path, profile_name: str) -> None:
    profiles_path = repo / 'integration/magicai-10-fixture/profiles.json'
    profiles = json.loads(profiles_path.read_text(encoding='utf-8'))
    profile = profiles[profile_name]
    catalogue = json.loads((repo / 'native-extensions/catalogue.json').read_text(encoding='utf-8'))

    extensions = host / 'app/Extensions'
    shutil.rmtree(extensions, ignore_errors=True)
    extensions.mkdir(parents=True)
    for folder in profile['folders']:
        shutil.copytree(build / folder, extensions / folder)

    fixture_app = repo / 'integration/magicai-10-fixture/app'
    _copy_tree(fixture_app, host / 'app')

    app_provider = host / 'app/Providers/AppServiceProvider.php'
    content = app_provider.read_text(encoding='utf-8')
    replacement = "public function register(): void\n    {\n        $this->app->register(MagicAIExtensionFixtureServiceProvider::class);\n    }"
    content, replacements = re.subn(
        r'public function register\(\): void\s*\{\s*//\s*\}',
        replacement,
        content,
        count=1,
    )
    if replacements != 1:
        raise RuntimeError('Unable to patch Laravel 10 AppServiceProvider::register().')
    app_provider.write_text(content, encoding='utf-8')

    providers_by_folder = {
        definition['folder']: definition['provider']
        for definition in catalogue['extensions'].values()
    }
    provider_list = ','.join(providers_by_folder[folder] for folder in profile['folders'])

    env_example = host / '.env.example'
    env_path = host / '.env'
    if not env_path.exists() and env_example.exists():
        shutil.copyfile(env_example, env_path)
    database = host / 'database/database.sqlite'
    database.parent.mkdir(parents=True, exist_ok=True)
    database.touch()
    values = {
        'APP_ENV': 'testing',
        'APP_DEBUG': 'false',
        'APP_KEY': 'base64:' + base64.b64encode(os.urandom(32)).decode(),
        'DB_CONNECTION': 'sqlite',
        'DB_DATABASE': str(database.resolve()),
        'CACHE_DRIVER': 'array',
        'SESSION_DRIVER': 'array',
        'QUEUE_CONNECTION': 'sync',
        'MAIL_MAILER': 'array',
        'MAGICAI_EXTENSION_PROVIDERS': provider_list,
        'WORKCORE_NATIVE_ENABLED': 'true',
        'WORKCORE_ENABLED': 'true' if profile['workcore_enabled'] else 'false',
        'WORKCORE_API_ROUTES_ENABLED': 'false',
        'WORKCORE_TENANCY_ROUTES_ENABLED': 'false',
    }
    values.update({f'WORKCORE_{flag}_ENABLED': 'true' for flag in MODULE_FLAGS})
    _set_env(env_path, values)


def main() -> int:
    parser = argparse.ArgumentParser(description='Prepare a Laravel 10 MagicAI-compatible native extension fixture.')
    parser.add_argument('--repo', type=Path, default=Path.cwd())
    parser.add_argument('--build', type=Path, required=True)
    parser.add_argument('--host', type=Path, required=True)
    parser.add_argument('--profile', required=True)
    args = parser.parse_args()
    prepare_fixture(args.repo.resolve(), args.build.resolve(), args.host.resolve(), args.profile)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
