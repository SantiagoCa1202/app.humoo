from __future__ import annotations

import json
import unittest
from pathlib import Path

from tests.golden.run_golden import run_manifest


class GoldenManifestTests(unittest.TestCase):
    def test_manifest_declares_the_three_official_documents_without_local_pdfs(self):
        manifest_path = Path(__file__).parent / "golden" / "manifest.json"
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))

        self.assertEqual(
            [entry["golden_id"] for entry in manifest["documents"]],
            ["GOLDEN-FEBRUARY-2026", "GOLDEN-APRIL-2026", "GOLDEN-AUGUST-2026"],
        )
        report = run_manifest(manifest, None)
        self.assertEqual(report["status"], "BLOCKED")
        self.assertTrue(all(item["status"] == "BLOCKED" for item in report["documents"]))


if __name__ == "__main__":
    unittest.main()
