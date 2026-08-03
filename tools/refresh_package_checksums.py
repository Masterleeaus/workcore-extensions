from __future__ import annotations

import argparse
import hashlib
import json
import re
from pathlib import Path
from typing import Any

SCHEMA_CREATE_PATTERN = re.compile(r"Schema::create\(\s*['\"]([^'\"]+)['\"]")
CHECKSUM_FILENAME = "files.sha256.json"
NATIVE_MANIFEST_PATH = Path("native-extensions/WorkCore/extension.manifest.json")


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def build_manifest(package_root: Path) -> dict[str, Any]:
    files = sorted(
        (
            path
            for path in package_root.rglob("*")
            if path.is_file() and path.name != CHECKSUM_FILENAME
        ),
        key=lambda path: path.relative_to(package_root).as_posix(),
    )
    entries = [
        {
            "bytes": path.stat().st_size,
            "path": path.relative_to(package_root).as_posix(),
            "sha256": sha256(path),
        }
        for path in files
    ]
    return {"file_count": len(entries), "files": entries}


def render_manifest(manifest: dict[str, Any]) -> str:
    return json.dumps(manifest, indent=2, sort_keys=False) + "\n"


def package_roots(repo_root: Path) -> list[Path]:
    packages = repo_root / "packages"
    if not packages.is_dir():
        raise FileNotFoundError(f"Missing packages directory: {packages}")
    return sorted((path for path in packages.iterdir() if path.is_dir()), key=lambda path: path.name)


def discover_owned_tables(repo_root: Path) -> set[str]:
    tables: set[str] = set()
    packages = repo_root / "packages"
    if not packages.is_dir():
        return tables

    for path in sorted(packages.rglob("*.php")):
        if "migrations" not in {part.lower() for part in path.parts}:
            continue
        tables.update(SCHEMA_CREATE_PATTERN.findall(path.read_text(encoding="utf-8")))
    return tables


def render_native_manifest(repo_root: Path) -> tuple[Path, str] | None:
    manifest_path = repo_root / NATIVE_MANIFEST_PATH
    if not manifest_path.is_file():
        return None

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    database = manifest.setdefault("database", {})
    existing = database.get("owned_tables", [])
    if not isinstance(existing, list):
        raise ValueError("WorkCore extension manifest database.owned_tables must be a list")
    database["owned_tables"] = sorted({str(table) for table in existing} | discover_owned_tables(repo_root))
    return manifest_path, render_manifest(manifest)


def refresh(repo_root: Path, *, check: bool, output_dir: Path | None) -> list[str]:
    mismatches: list[str] = []

    native_manifest = render_native_manifest(repo_root)
    if native_manifest is not None:
        manifest_path, rendered = native_manifest
        existing = manifest_path.read_text(encoding="utf-8")
        if existing != rendered:
            mismatches.append("native-owned-tables")
        if output_dir is not None:
            output_dir.mkdir(parents=True, exist_ok=True)
            (output_dir / "WorkCore.extension.manifest.json").write_text(rendered, encoding="utf-8")
        elif not check:
            manifest_path.write_text(rendered, encoding="utf-8")

    for package_root in package_roots(repo_root):
        rendered = render_manifest(build_manifest(package_root))
        manifest_path = package_root / CHECKSUM_FILENAME
        existing = manifest_path.read_text(encoding="utf-8") if manifest_path.is_file() else None
        if existing != rendered:
            mismatches.append(package_root.name)

        if output_dir is not None:
            output_dir.mkdir(parents=True, exist_ok=True)
            (output_dir / f"{package_root.name}.files.sha256.json").write_text(rendered, encoding="utf-8")
        elif not check:
            manifest_path.write_text(rendered, encoding="utf-8")

    return mismatches


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Refresh or verify deterministic WorkCore package checksums and native ownership metadata."
    )
    parser.add_argument("--repo", type=Path, default=Path.cwd())
    parser.add_argument("--check", action="store_true", help="Fail when committed integrity metadata differs from generated content.")
    parser.add_argument("--output-dir", type=Path, help="Write generated metadata to a separate directory.")
    args = parser.parse_args()

    mismatches = refresh(args.repo.resolve(), check=args.check, output_dir=args.output_dir)
    if mismatches:
        print("Repository integrity metadata differs: " + ", ".join(mismatches))
        return 1 if args.check else 0

    print("All repository integrity metadata is current.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
