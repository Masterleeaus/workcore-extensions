from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from tools.refresh_package_checksums import build_manifest, refresh, render_manifest


class PackageChecksumRefreshTests(unittest.TestCase):
    def test_manifest_is_deterministic_and_excludes_itself(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            package = Path(temp) / "package"
            package.mkdir()
            (package / "z.txt").write_text("z", encoding="utf-8")
            (package / "a.txt").write_text("alpha", encoding="utf-8")
            (package / "files.sha256.json").write_text("stale", encoding="utf-8")

            first = build_manifest(package)
            second = build_manifest(package)

            self.assertEqual(first, second)
            self.assertEqual(["a.txt", "z.txt"], [entry["path"] for entry in first["files"]])
            self.assertEqual(2, first["file_count"])
            self.assertTrue(render_manifest(first).endswith("\n"))

    def test_refresh_writes_and_check_detects_drift(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            repo = Path(temp)
            package = repo / "packages/example"
            package.mkdir(parents=True)
            (package / "file.txt").write_text("one", encoding="utf-8")

            self.assertEqual(["example"], refresh(repo, check=False, output_dir=None))
            self.assertEqual([], refresh(repo, check=True, output_dir=None))

            manifest = json.loads((package / "files.sha256.json").read_text(encoding="utf-8"))
            self.assertEqual(1, manifest["file_count"])

            (package / "file.txt").write_text("two", encoding="utf-8")
            self.assertEqual(["example"], refresh(repo, check=True, output_dir=None))

    def test_output_directory_does_not_modify_committed_manifest(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            repo = Path(temp)
            package = repo / "packages/example"
            package.mkdir(parents=True)
            committed = package / "files.sha256.json"
            committed.write_text("stale\n", encoding="utf-8")
            (package / "file.txt").write_text("data", encoding="utf-8")
            output = repo / "generated"

            self.assertEqual(["example"], refresh(repo, check=False, output_dir=output))
            self.assertEqual("stale\n", committed.read_text(encoding="utf-8"))
            self.assertTrue((output / "example.files.sha256.json").is_file())


if __name__ == "__main__":
    unittest.main()
