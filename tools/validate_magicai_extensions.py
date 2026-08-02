#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
from typing import Any

LEGACY_FIELDS = {"name", "type", "version", "description", "support_telegram"}
FORBIDDEN_PARTS = {".git", "vendor", "node_modules"}
RUNTIME_RESIDUE = (
    "storage/framework/sessions",
    "storage/logs",
    "storage/framework/cache",
    "livewire-tmp",
)


def _json(path: Path, errors: list[str]) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8-sig"))
    except (OSError, UnicodeError, json.JSONDecodeError) as error:
        errors.append(f"{path.name}: {error}")
        return {}
    if not isinstance(value, dict):
        errors.append(f"{path.name}: root must be an object")
        return {}
    return value


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _provider_path(root: Path, folder: str, provider: str) -> Path | None:
    prefix = f"App\\Extensions\\{folder}\\"
    if not provider.startswith(prefix):
        return None
    return root / (provider[len(prefix) :].replace("\\", "/") + ".php")


def _validate_checksums(root: Path, errors: list[str]) -> None:
    path = root / "checksums.sha256.json"
    if not path.exists():
        errors.append("checksums.sha256.json is missing")
        return
    data = _json(path, errors)
    entries = data.get("files")
    if not isinstance(entries, list):
        errors.append("checksums.sha256.json: files must be a list")
        return

    declared: set[str] = set()
    for entry in entries:
        if not isinstance(entry, dict):
            errors.append("checksums.sha256.json: entry must be an object")
            continue
        relative = entry.get("path")
        if not isinstance(relative, str) or not relative:
            errors.append("checksums.sha256.json: invalid path")
            continue
        if relative in declared:
            errors.append(f"checksums.sha256.json: duplicate {relative}")
            continue
        declared.add(relative)
        file = root / relative
        if not file.is_file():
            errors.append(f"checksummed file missing: {relative}")
            continue
        if entry.get("bytes") != file.stat().st_size:
            errors.append(f"checksum byte mismatch: {relative}")
        if entry.get("sha256") != _sha256(file):
            errors.append(f"checksum mismatch: {relative}")

    actual = {
        file.relative_to(root).as_posix()
        for file in root.rglob("*")
        if file.is_file() and file.name != "checksums.sha256.json"
    }
    for relative in sorted(actual - declared):
        errors.append(f"file is not checksummed: {relative}")
    for relative in sorted(declared - actual):
        errors.append(f"checksum references absent file: {relative}")


def validate_extension_root(root: Path) -> list[str]:
    root = root.resolve()
    errors: list[str] = []
    legacy = _json(root / "extension.json", errors)
    sidecar = _json(root / "extension.manifest.json", errors)

    if set(legacy) != LEGACY_FIELDS:
        errors.append("extension.json must contain exactly the five legacy fields")
    if legacy.get("type") != "extension":
        errors.append("extension.json type must be extension")
    if legacy.get("name") != sidecar.get("name"):
        errors.append("manifest names differ")
    if legacy.get("version") != sidecar.get("version"):
        errors.append("manifest versions differ")

    folder = sidecar.get("folder")
    provider = sidecar.get("provider")
    if folder != root.name:
        errors.append(f"manifest folder {folder!r} does not match {root.name!r}")
    if not isinstance(folder, str) or not isinstance(provider, str):
        errors.append("manifest folder/provider must be strings")
    else:
        provider_path = _provider_path(root, folder, provider)
        if provider_path is None:
            errors.append("provider is outside declared extension namespace")
        elif not provider_path.is_file():
            errors.append(f"provider file is missing: {provider_path.relative_to(root)}")

    family = sidecar.get("family")
    dependencies = sidecar.get("dependencies", {})
    database = sidecar.get("database", {})
    if family == "vertical-suite":
        if not (root / "Runtime/Domains/WorkCore/WorkCoreServiceProvider.php").is_file():
            errors.append("parent runtime kernel is missing")
        if not database.get("migrations"):
            errors.append("parent extension must own migrations")
    elif family == "addon":
        if (root / "Runtime").exists():
            errors.append("add-on must not duplicate the WorkCore runtime")
        if (root / "database/migrations").exists():
            errors.append("add-on must not own migrations")
        parent = dependencies.get("parent") if isinstance(dependencies, dict) else None
        if not isinstance(parent, dict) or parent.get("key") != "workcore":
            errors.append("add-on must declare WorkCore as parent")
        if database.get("migrations") is not False:
            errors.append("add-on manifest migrations must be false")

    for file in root.rglob("*"):
        if not file.is_file():
            continue
        relative = file.relative_to(root).as_posix()
        parts = set(file.relative_to(root).parts)
        if parts & FORBIDDEN_PARTS:
            errors.append(f"forbidden dependency directory: {relative}")
        if file.name == ".env" or file.name.startswith(".env."):
            errors.append(f"secret environment file is forbidden: {relative}")
        if file.suffix.lower() == ".zip":
            errors.append(f"nested archive is forbidden: {relative}")
        if any(marker in relative for marker in RUNTIME_RESIDUE):
            errors.append(f"runtime residue is forbidden: {relative}")

    _validate_checksums(root, errors)
    return errors


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate generated MagicAI extension folders.")
    parser.add_argument("paths", nargs="+", type=Path)
    args = parser.parse_args()
    failed = False
    for path in args.paths:
        errors = validate_extension_root(path)
        if errors:
            failed = True
            for error in errors:
                print(f"ERROR {path.name}: {error}")
        else:
            print(f"OK {path.name}")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
