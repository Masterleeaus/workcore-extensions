from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path
from typing import Any

if __package__ in {None, ''}:
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from tools.build_extensions import PACKAGE_VERSION

PACKAGE_DIRECTORIES = {
    'workcore/shared-foundation': 'workcore-shared-foundation',
    'workcore/business-network': 'workcore-business-network',
    'workcore/commercial': 'workcore-commercial',
    'workcore/work-operations': 'workcore-work-operations',
    'workcore/property-operations': 'workcore-property-operations',
    'workcore/workforce-assurance': 'workcore-workforce-assurance',
}


def load_profiles(path: Path) -> dict[str, dict[str, Any]]:
    payload = json.loads(path.read_text(encoding='utf-8'))
    if not isinstance(payload, dict) or not payload:
        raise ValueError('Install profiles must be a non-empty JSON object.')
    for name, profile in payload.items():
        if not isinstance(profile, dict) or not isinstance(profile.get('packages'), list):
            raise ValueError(f'Install profile [{name}] must define a packages list.')
    return payload


def generate_host_composer(
    *,
    host_composer: Path,
    packages_root: Path,
    profiles_path: Path,
    profile_name: str,
    output_path: Path,
) -> dict[str, Any]:
    profiles = load_profiles(profiles_path)
    if profile_name not in profiles:
        raise KeyError(f'Unknown install profile [{profile_name}].')

    selected = profiles[profile_name]['packages']
    unknown = sorted(set(selected) - set(PACKAGE_DIRECTORIES))
    if unknown:
        raise ValueError(f'Profile [{profile_name}] references unknown packages: {unknown}')
    if 'workcore/shared-foundation' not in selected:
        raise ValueError(f'Profile [{profile_name}] must include workcore/shared-foundation.')

    composer = json.loads(host_composer.read_text(encoding='utf-8'))
    require = dict(composer.get('require', {}))
    for package_name in PACKAGE_DIRECTORIES:
        require.pop(package_name, None)
    for package_name in selected:
        require[package_name] = PACKAGE_VERSION
    composer['require'] = dict(sorted(require.items()))

    host_root = host_composer.parent.resolve()
    repositories = [
        repository
        for repository in composer.get('repositories', [])
        if not (isinstance(repository, dict) and repository.get('type') == 'path' and 'workcore-' in str(repository.get('url', '')))
    ]
    for package_name in selected:
        package_path = (packages_root / PACKAGE_DIRECTORIES[package_name]).resolve()
        repositories.append({
            'type': 'path',
            'url': Path(os.path.relpath(package_path, host_root)).as_posix(),
            'options': {'symlink': False},
            'canonical': True,
        })
    composer['repositories'] = repositories

    composer.setdefault('extra', {}).setdefault('workcore', {})['install_profile'] = profile_name
    composer['extra']['workcore']['package_version'] = PACKAGE_VERSION

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(composer, indent=2, sort_keys=True) + '\n', encoding='utf-8')
    return composer


def main() -> int:
    parser = argparse.ArgumentParser(description='Generate a MagicAI host composer.json for a WorkCore install profile.')
    parser.add_argument('--host-composer', type=Path, required=True)
    parser.add_argument('--packages-root', type=Path, required=True)
    parser.add_argument('--profiles', type=Path, required=True)
    parser.add_argument('--profile', required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    generate_host_composer(
        host_composer=args.host_composer,
        packages_root=args.packages_root,
        profiles_path=args.profiles,
        profile_name=args.profile,
        output_path=args.output,
    )
    print(args.output)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
