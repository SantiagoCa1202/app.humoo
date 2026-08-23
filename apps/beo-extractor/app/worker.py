from __future__ import annotations

import hashlib
import logging
import time
from pathlib import Path
from tempfile import TemporaryDirectory
from typing import Any, Callable

from .contracts import ContractValidationError, validate_result
from .engine import extract_document
from .transport import ExtractionApiClient, WorkerHttpError
from .worker_config import WorkerConfig

LOGGER = logging.getLogger("humoo.beo.worker")
Engine = Callable[..., dict[str, Any]]


class ExtractionWorker:
    def __init__(self, config: WorkerConfig, client: ExtractionApiClient | Any, engine: Engine = extract_document) -> None:
        self.config = config
        self.client = client
        self.engine = engine
        self.config.ensure_temp_dir()

    def run_once(self) -> bool:
        try:
            claimed = self.client.claim()
        except WorkerHttpError as exc:
            LOGGER.warning("claim unavailable retryable=%s", exc.retryable)
            return False

        if not claimed:
            return False

        run = claimed.get("run") or {}
        job = claimed.get("job")
        run_id = str(run.get("id") or "")
        if not run_id or not isinstance(job, dict):
            LOGGER.error("Laravel returned an invalid claimed job envelope")
            return False

        try:
            with TemporaryDirectory(dir=self.config.temp_dir, prefix=f"run-{run_id}-") as temp_dir:
                document_path = Path(temp_dir) / "source.pdf"
                document_path.write_bytes(self.client.download_document(run_id))
                self._verify_checksum(document_path, job)
                result = self.engine(job, document_path=document_path)
                validate_result(result)
                self.client.heartbeat(run_id)
                self.client.submit_result(run_id, result)
            return True
        except ContractValidationError as exc:
            self._submit_failure(run_id, "CONTRACT_VALIDATION_FAILED", str(exc), retryable=False)
        except WorkerHttpError as exc:
            if exc.status_code is not None and not exc.retryable:
                LOGGER.error("terminal worker API error status=%s", exc.status_code)
                return False
            self._submit_failure(run_id, "WORKER_NETWORK_ERROR", str(exc), retryable=True)
        except (OSError, ValueError) as exc:
            self._submit_failure(run_id, "EXTRACTION_WORKER_ERROR", str(exc), retryable=False)
        return False

    def run_forever(self) -> None:
        backoff = self.config.poll_interval_seconds
        while True:
            processed = self.run_once()
            if processed:
                backoff = self.config.poll_interval_seconds
            else:
                time.sleep(backoff)
                backoff = min(60.0, max(self.config.poll_interval_seconds, backoff * 2))

    def _verify_checksum(self, document_path: Path, job: dict[str, Any]) -> None:
        expected = str(job.get("document", {}).get("sha256", "")).lower()
        actual = hashlib.sha256(document_path.read_bytes()).hexdigest().lower()
        if not expected or actual != expected:
            raise ValueError("Downloaded document checksum does not match the ExtractionJob.")

    def _submit_failure(self, run_id: str, code: str, message: str, retryable: bool) -> None:
        if not run_id:
            return
        try:
            self.client.submit_failure(run_id, code, message, retryable)
        except WorkerHttpError as exc:
            LOGGER.error("failure submission unavailable retryable=%s", exc.retryable)


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    config = WorkerConfig.from_env()
    worker = ExtractionWorker(
        config,
        ExtractionApiClient(
            config.api_base_url,
            config.worker_id,
            config.worker_token,
            config.request_timeout_seconds,
        ),
    )
    worker.run_forever()


if __name__ == "__main__":
    main()
