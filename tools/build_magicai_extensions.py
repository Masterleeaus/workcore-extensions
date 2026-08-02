#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import stat
import sys
import zipfile
from dataclasses import asdict, dataclass
from pathlib import Path

if __package__ in {None, ""}:
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from tools.validate_magicai_extensions import validate_extension_root

FIXED_ZIP_TIME = (1980, 1, 1, 0, 0, 0)

AGGREGATE_PROVIDER_PATHS = {
    "Domains/WorkCore/Providers/BusinessNetworkServiceProvider.php",
    "Domains/WorkCore/Providers/CommercialServiceProvider.php",
    "Domains/WorkCore/Providers/WorkOperationsServiceProvider.php",
    "Domains/WorkCore/Providers/PropertyOperationsServiceProvider.php",
    "Domains/WorkCore/Providers/WorkforceAssuranceServiceProvider.php",
}


@dataclass(frozen=True)
class ExtensionArtifact:
    key: str
    folder: str
    zip_path: Path
    bytes: int
    sha256: str
    file_count: int
    runtime_file_count: int


@dataclass(frozen=True)
class BuildReport:
    release_version: str
    extensions: tuple[ExtensionArtifact, ...]


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _copy_file(source: Path, destination: Path) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, destination)
    destination.chmod(source.stat().st_mode & 0o777)


def _merge_runtime(repo_root: Path, destination: Path) -> int:
    destination.mkdir(parents=True, exist_ok=True)
    seen: dict[str, str] = {}
    count = 0
    for package in sorted((repo_root / "packages").iterdir(), key=lambda item: item.name):
        source_root = package / "src"
        if not source_root.is_dir():
            continue
        for source in sorted(source_root.rglob("*")):
            if not source.is_file():
                continue
            relative = source.relative_to(source_root).as_posix()
            digest = _sha256(source)
            if relative in seen:
                if seen[relative] != digest:
                    raise RuntimeError(f"conflicting runtime source: {relative}")
                continue
            seen[relative] = digest
            if relative in AGGREGATE_PROVIDER_PATHS:
                archive = destination.parent / "docs/source/aggregate-providers" / Path(relative).name
                _copy_file(source, archive)
                continue
            _copy_file(source, destination / relative)
            count += 1
    return count


def _write_checksums(root: Path) -> None:
    entries = []
    for file in sorted(root.rglob("*")):
        if not file.is_file() or file.name == "checksums.sha256.json":
            continue
        entries.append({
            "path": file.relative_to(root).as_posix(),
            "bytes": file.stat().st_size,
            "sha256": _sha256(file),
        })
    payload = {"algorithm": "sha256", "file_count": len(entries), "files": entries}
    (root / "checksums.sha256.json").write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")


def _write_deterministic_zip(source_root: Path, zip_path: Path) -> None:
    zip_path.parent.mkdir(parents=True, exist_ok=True)
    zip_path.unlink(missing_ok=True)
    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for file in sorted(source_root.rglob("*")):
            if not file.is_file():
                continue
            arcname = f"{source_root.name}/{file.relative_to(source_root).as_posix()}"
            info = zipfile.ZipInfo(arcname, FIXED_ZIP_TIME)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (stat.S_IFREG | (file.stat().st_mode & 0o777)) << 16
            archive.writestr(info, file.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)


def build_magicai_extensions(repo_root: Path, output_root: Path) -> BuildReport:
    repo_root = repo_root.resolve()
    output_root = output_root.resolve()
    native_root = repo_root / "native-extensions"
    catalogue = json.loads((native_root / "catalogue.json").read_text(encoding="utf-8"))
    release_version = str(catalogue["release_version"])

    if output_root.exists():
        shutil.rmtree(output_root)
    output_root.mkdir(parents=True)
    zip_root = output_root / "zips"
    artifacts: list[ExtensionArtifact] = []

    for key, definition in catalogue["extensions"].items():
        folder = str(definition["folder"])
        template_root = native_root / folder
        extension_root = output_root / folder
        shutil.copytree(template_root, extension_root)

        runtime_count = 0
        if key == catalogue["parent_key"]:
            runtime_count = _merge_runtime(repo_root, extension_root / "Runtime")
            docs = extension_root / "docs"
            docs.mkdir(exist_ok=True)
            _copy_file(repo_root / "ownership-manifest.json", docs / "ownership-manifest.json")
            _copy_file(native_root / "catalogue.json", docs / "native-extension-catalogue.json")

        _write_checksums(extension_root)
        errors = validate_extension_root(extension_root)
        if errors:
            raise RuntimeError(f"invalid generated extension {folder}: " + "; ".join(errors))

        zip_path = zip_root / f"{folder}-{release_version}.zip"
        _write_deterministic_zip(extension_root, zip_path)
        artifacts.append(ExtensionArtifact(
            key=key,
            folder=folder,
            zip_path=zip_path,
            bytes=zip_path.stat().st_size,
            sha256=_sha256(zip_path),
            file_count=sum(1 for path in extension_root.rglob("*") if path.is_file()),
            runtime_file_count=runtime_count,
        ))

    report = BuildReport(release_version=release_version, extensions=tuple(artifacts))
    report_payload = {
        "release_version": release_version,
        "extensions": [
            {
                **asdict(item),
                "zip_path": item.zip_path.relative_to(output_root).as_posix(),
            }
            for item in report.extensions
        ],
    }
    (output_root / "release-report.json").write_text(
        json.dumps(report_payload, indent=2) + "\n",
        encoding="utf-8",
    )
    return report


def main() -> int:
    parser = argparse.ArgumentParser(description="Build MagicAI-native WorkCore extension releases.")
    parser.add_argument("--repo", type=Path, default=Path.cwd())
    parser.add_argument("--output", type=Path, default=Path("dist/magicai-extensions"))
    args = parser.parse_args()
    report = build_magicai_extensions(args.repo, args.output)
    for item in report.extensions:
        print(f"{item.folder}: {item.bytes} bytes {item.sha256}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
