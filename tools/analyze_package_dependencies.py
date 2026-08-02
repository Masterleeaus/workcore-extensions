from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from pathlib import Path
from typing import Any

OPTIONAL_MODULES = {
    'CRM', 'Catalogue', 'Support', 'Knowledge', 'KnowledgeBase', 'Reviews', 'Territories',
    'Feedback', 'Wizards', 'Finance', 'Payroll', 'Inventory', 'Supply', 'TitanVault',
    'TrustAccounting', 'Operations', 'Scheduling', 'Dispatch', 'RecurringServices', 'Forms',
    'Repairs', 'Fleet', 'QRCode', 'Premises', 'Assets', 'Documents', 'Workforce', 'People',
    'AttendanceVerification', 'Rosters', 'Attendance', 'Compliance', 'Assurance', 'Credentials',
    'NDIS',
}

DIRECT_OPTIONAL_INCLUDE = re.compile(
    r'\b(?:require|require_once|include|include_once)\s+__DIR__[^;]*System/Modules/([A-Za-z0-9_]+)',
)
CLASS_REFERENCE = re.compile(r'App\\Domains\\WorkCore\\[A-Za-z0-9_\\]+')
NAMESPACE = re.compile(r'namespace\s+([^;]+);')
DECLARATION = re.compile(r'\b(?:class|interface|trait|enum)\s+(\w+)')


def find_optional_path_includes(shared_package_root: Path) -> list[dict[str, Any]]:
    findings: list[dict[str, Any]] = []
    for path in sorted(shared_package_root.rglob('*.php')):
        text = path.read_text(encoding='utf-8', errors='ignore')
        for line_number, line in enumerate(text.splitlines(), start=1):
            for match in DIRECT_OPTIONAL_INCLUDE.finditer(line):
                module = match.group(1)
                if module in OPTIONAL_MODULES:
                    findings.append({
                        'path': path.relative_to(shared_package_root).as_posix(),
                        'line': line_number,
                        'module': module,
                        'source': line.strip(),
                    })
    return findings


def _declared_classes(packages_root: Path) -> dict[str, str]:
    owners: dict[str, str] = {}
    for package_root in sorted(path for path in packages_root.iterdir() if path.is_dir()):
        for source in package_root.rglob('*.php'):
            text = source.read_text(encoding='utf-8', errors='ignore')
            namespace = NAMESPACE.search(text)
            if namespace is None:
                continue
            for declaration in DECLARATION.finditer(text):
                owners[f"{namespace.group(1).strip()}\\{declaration.group(1)}"] = package_root.name
    return owners


def analyze_dependencies(packages_root: Path) -> dict[str, Any]:
    class_owners = _declared_classes(packages_root)
    edges: Counter[tuple[str, str]] = Counter()
    examples: dict[tuple[str, str], list[dict[str, str]]] = {}

    for package_root in sorted(path for path in packages_root.iterdir() if path.is_dir()):
        for source in package_root.rglob('*.php'):
            text = source.read_text(encoding='utf-8', errors='ignore')
            for reference in CLASS_REFERENCE.findall(text):
                target = class_owners.get(reference.rstrip('\\'))
                if target is None or target == package_root.name:
                    continue
                edge = (package_root.name, target)
                edges[edge] += 1
                samples = examples.setdefault(edge, [])
                if len(samples) < 5:
                    samples.append({
                        'path': source.relative_to(package_root).as_posix(),
                        'class': reference.rstrip('\\'),
                    })

    return {
        'packages': sorted(path.name for path in packages_root.iterdir() if path.is_dir()),
        'edges': [
            {
                'source': source,
                'target': target,
                'reference_count': count,
                'examples': examples[(source, target)],
            }
            for (source, target), count in sorted(edges.items())
        ],
        'shared_optional_path_includes': find_optional_path_includes(
            packages_root / 'workcore-shared-foundation'
        ),
    }


def main() -> int:
    parser = argparse.ArgumentParser(description='Analyze cross-package WorkCore references and unsafe includes.')
    parser.add_argument('--packages-root', type=Path, required=True)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    report = analyze_dependencies(args.packages_root)
    rendered = json.dumps(report, indent=2, sort_keys=True) + '\n'
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(rendered, encoding='utf-8')
    else:
        print(rendered, end='')
    return 1 if report['shared_optional_path_includes'] else 0


if __name__ == '__main__':
    raise SystemExit(main())
