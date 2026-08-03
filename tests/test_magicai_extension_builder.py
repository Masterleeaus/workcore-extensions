from __future__ import annotations

import hashlib
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

from tools.build_magicai_extensions import build_magicai_extensions
from tools.validate_magicai_extensions import validate_extension_root

REPO_ROOT = Path(__file__).resolve().parents[1]
EXPECTED_FOLDERS = {
    "WorkCore",
    "WorkCoreBusinessNetwork",
    "WorkCoreCommercial",
    "WorkCoreWorkOperations",
    "WorkCorePropertyOperations",
    "WorkCoreWorkforceAssurance",
}


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def expected_runtime_paths(aggregate_names: set[str]) -> set[str]:
    paths: set[str] = set()
    for package_src in sorted((REPO_ROOT / "packages").glob("*/src")):
        for source in package_src.rglob("*"):
            if not source.is_file():
                continue
            relative = source.relative_to(package_src).as_posix()
            if Path(relative).parent.as_posix() == "Domains/WorkCore/Providers" and Path(relative).name in aggregate_names:
                continue
            paths.add(relative)
    return paths


class MagicAIExtensionBuilderTests(unittest.TestCase):
    def test_builder_cli_runs_directly_from_repository_root(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "release"
            result = subprocess.run(
                [
                    sys.executable,
                    "tools/build_magicai_extensions.py",
                    "--repo",
                    ".",
                    "--output",
                    str(output),
                ],
                cwd=REPO_ROOT,
                text=True,
                capture_output=True,
                check=False,
            )
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertTrue((output / "release-report.json").is_file())

    def test_builder_creates_six_valid_release_folders_and_zips(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "release"
            report = build_magicai_extensions(REPO_ROOT, output)

            self.assertEqual(EXPECTED_FOLDERS, {item.folder for item in report.extensions})
            for item in report.extensions:
                extension_root = output / item.folder
                self.assertTrue(extension_root.is_dir())
                self.assertTrue(item.zip_path.is_file())
                self.assertEqual([], validate_extension_root(extension_root))
                self.assertEqual(item.sha256, sha256(item.zip_path))
                self.assertGreater(item.bytes, 0)

    def test_parent_release_preserves_every_ownership_tracked_file(self) -> None:
        ownership = json.loads((REPO_ROOT / "ownership-manifest.json").read_text(encoding="utf-8"))
        aggregate_names = {
            "BusinessNetworkServiceProvider.php",
            "CommercialServiceProvider.php",
            "WorkOperationsServiceProvider.php",
            "PropertyOperationsServiceProvider.php",
            "WorkforceAssuranceServiceProvider.php",
        }
        expected_paths = expected_runtime_paths(aggregate_names)
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "release"
            report = build_magicai_extensions(REPO_ROOT, output)
            runtime = output / "WorkCore/Runtime"
            archive = output / "WorkCore/docs/source/aggregate-providers"
            actual_paths = {
                path.relative_to(runtime).as_posix()
                for path in runtime.rglob("*")
                if path.is_file()
            }
            self.assertEqual(expected_paths, actual_paths)
            parent = next(item for item in report.extensions if item.folder == "WorkCore")
            self.assertEqual(len(expected_paths), parent.runtime_file_count)

            for entry in ownership["files"]:
                package_path = REPO_ROOT / entry["destination"]
                relative = Path(entry["destination"].split("/src/", 1)[1])
                if relative.parent.as_posix() == "Domains/WorkCore/Providers" and relative.name in aggregate_names:
                    release_path = archive / relative.name
                else:
                    release_path = runtime / relative
                self.assertTrue(release_path.is_file(), relative.as_posix())
                self.assertEqual(sha256(package_path), sha256(release_path), relative.as_posix())

    def test_parent_runtime_excludes_aggregate_providers_but_archives_their_source(self) -> None:
        aggregate_files = {
            "BusinessNetworkServiceProvider.php",
            "CommercialServiceProvider.php",
            "WorkOperationsServiceProvider.php",
            "PropertyOperationsServiceProvider.php",
            "WorkforceAssuranceServiceProvider.php",
        }
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "release"
            build_magicai_extensions(REPO_ROOT, output)
            runtime_providers = output / "WorkCore/Runtime/Domains/WorkCore/Providers"
            archive = output / "WorkCore/docs/source/aggregate-providers"
            for filename in aggregate_files:
                self.assertFalse((runtime_providers / filename).exists(), filename)
                self.assertTrue((archive / filename).is_file(), filename)

    def test_addons_do_not_duplicate_runtime_or_migrations(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "release"
            build_magicai_extensions(REPO_ROOT, output)
            for folder in EXPECTED_FOLDERS - {"WorkCore"}:
                root = output / folder
                self.assertFalse((root / "Runtime").exists())
                self.assertFalse((root / "database/migrations").exists())
                self.assertEqual(0, len(list(root.rglob("*Migration*.php"))))

    def test_zip_output_is_deterministic(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            base = Path(temp)
            first = build_magicai_extensions(REPO_ROOT, base / "first")
            second = build_magicai_extensions(REPO_ROOT, base / "second")
            first_hashes = {item.folder: item.sha256 for item in first.extensions}
            second_hashes = {item.folder: item.sha256 for item in second.extensions}
            self.assertEqual(first_hashes, second_hashes)

    def test_validator_rejects_secrets_runtime_residue_and_nested_archives(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            output = Path(temp) / "release"
            build_magicai_extensions(REPO_ROOT, output)
            root = output / "WorkCore"
            (root / ".env").write_text("APP_KEY=secret\n", encoding="utf-8")
            (root / "payload.zip").write_bytes(b"not-a-real-zip")
            session = root / "storage/framework/sessions/session"
            session.parent.mkdir(parents=True)
            session.write_text("runtime", encoding="utf-8")

            errors = validate_extension_root(root)
            self.assertTrue(any(".env" in error for error in errors))
            self.assertTrue(any("nested archive" in error for error in errors))
            self.assertTrue(any("runtime residue" in error for error in errors))


if __name__ == "__main__":
    unittest.main()
