from __future__ import annotations

import hashlib
import json
import tempfile
import unittest
from pathlib import Path

from app.worker import ExtractionWorker
from app.worker_config import WorkerConfig


class FakeClient:
    def __init__(self, job: dict, document: bytes) -> None:
        self.job = job
        self.document = document
        self.submitted: list[dict] = []
        self.failures: list[dict] = []

    def claim(self):
        if self.job is None:
            return None
        job, self.job = self.job, None
        return {"run": {"id": job["extraction_run_id"]}, "job": job}

    def download_document(self, run_id: str) -> bytes:
        return self.document

    def heartbeat(self, run_id: str):
        return {"data": {"id": run_id}}

    def submit_result(self, run_id: str, result: dict):
        self.submitted.append(result)
        return {"data": {"id": run_id}}

    def submit_failure(self, run_id: str, code: str, message: str, retryable: bool):
        self.failures.append({"run_id": run_id, "code": code, "retryable": retryable})
        return {"data": {"id": run_id}}


def valid_job(document: bytes) -> dict:
    checksum = hashlib.sha256(document).hexdigest()
    return {
        "schema_version": "1.0.0",
        "extraction_run_id": "run-worker-1",
        "document_id": "doc-worker-1",
        "import_batch_id": "batch-worker-1",
        "correlation_id": "corr-worker-1",
        "document": {
            "filename": "sample.pdf",
            "mime_type": "application/pdf",
            "sha256": checksum,
            "file_size": len(document),
            "source_reference": None,
            "provider_hint": "humoo-beo-extractor",
            "language_hints": [],
        },
        "options": {
            "use_ocr": False,
            "include_layout": True,
            "include_source_trace": True,
            "parser_profile": None,
        },
        "requested_at": "2026-08-23T00:00:00Z",
    }


class WorkerTests(unittest.TestCase):
    def test_processes_claimed_job_and_cleans_temporary_file(self):
        document = b"small-pdf-fixture"
        job = valid_job(document)
        contract_example = Path(__file__).resolve().parents[3] / "contracts" / "beo-extraction" / "v1" / "examples" / "partial-result.json"
        result = json.loads(contract_example.read_text(encoding="utf-8"))
        result.update({
            "extraction_run_id": job["extraction_run_id"],
            "document_id": job["document_id"],
            "import_batch_id": job["import_batch_id"],
            "correlation_id": job["correlation_id"],
        })
        client = FakeClient(job, document)
        with tempfile.TemporaryDirectory() as temp_dir:
            config = WorkerConfig("http://api.test", "worker-1", "secret", temp_dir=Path(temp_dir))
            worker = ExtractionWorker(config, client, engine=lambda received, document_path: result)
            self.assertTrue(worker.run_once())
            self.assertEqual(len(client.submitted), 1)
            self.assertEqual(list(Path(temp_dir).iterdir()), [])

    def test_invalid_engine_result_is_not_submitted(self):
        document = b"small-pdf-fixture"
        job = valid_job(document)
        client = FakeClient(job, document)
        with tempfile.TemporaryDirectory() as temp_dir:
            config = WorkerConfig("http://api.test", "worker-1", "secret", temp_dir=Path(temp_dir))
            worker = ExtractionWorker(config, client, engine=lambda received, document_path: {"invalid": True})
            self.assertFalse(worker.run_once())
            self.assertEqual(len(client.submitted), 0)
            self.assertEqual(client.failures[0]["code"], "CONTRACT_VALIDATION_FAILED")


if __name__ == "__main__":
    unittest.main()
