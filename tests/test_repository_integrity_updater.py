from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path

from tools.update_repository_integrity import checksum_payload, discover_owned_tables


class RepositoryIntegrityUpdaterTests(unittest.TestCase):
    def test_checksum_payload_is_deterministic_and_excludes_itself(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root / "z.txt").write_text("z", encoding="utf-8")
            (root / "a.txt").write_text("alpha", encoding="utf-8")
            (root / "files.sha256.json").write_text("stale", encoding="utf-8")

            first = checksum_payload(root)
            second = checksum_payload(root)

            self.assertEqual(first, second)
            self.assertEqual(2, first["file_count"])
            self.assertEqual(["a.txt", "z.txt"], [item["path"] for item in first["files"]])
            self.assertNotIn("files.sha256.json", {item["path"] for item in first["files"]})
            json.dumps(first)

    def test_owned_table_discovery_reads_schema_create_calls(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            repo = Path(temp)
            migrations = repo / "packages/example/src/Domains/WorkCore/Database/Migrations"
            migrations.mkdir(parents=True)
            (migrations / "one.php").write_text(
                "Schema::create('tz_alpha', function () {});\n"
                'Schema::create("tz_beta", function () {});\n'
                "Schema::table('tz_existing', function () {});\n",
                encoding="utf-8",
            )

            self.assertEqual({"tz_alpha", "tz_beta"}, discover_owned_tables(repo))


if __name__ == "__main__":
    unittest.main()
