from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class WorkerConfig:
    api_base_url: str
    worker_id: str
    worker_token: str
    poll_interval_seconds: float = 5.0
    job_lease_seconds: int = 300
    request_timeout_seconds: float = 30.0
    temp_dir: Path = Path("/tmp/humoo-beo")

    @classmethod
    def from_env(cls) -> WorkerConfig:
        required = {
            "HUMOO_API_BASE_URL": os.getenv("HUMOO_API_BASE_URL", "").strip(),
            "HUMOO_WORKER_ID": os.getenv("HUMOO_WORKER_ID", "").strip(),
            "HUMOO_WORKER_TOKEN": os.getenv("HUMOO_WORKER_TOKEN", "").strip(),
        }
        missing = [key for key, value in required.items() if not value]
        if missing:
            raise ValueError(f"Missing worker environment variables: {', '.join(missing)}")

        return cls(
            api_base_url=required["HUMOO_API_BASE_URL"].rstrip("/"),
            worker_id=required["HUMOO_WORKER_ID"],
            worker_token=required["HUMOO_WORKER_TOKEN"],
            poll_interval_seconds=max(1.0, float(os.getenv("POLL_INTERVAL_SECONDS", "5"))),
            job_lease_seconds=max(30, int(os.getenv("JOB_LEASE_SECONDS", "300"))),
            request_timeout_seconds=max(1.0, float(os.getenv("REQUEST_TIMEOUT_SECONDS", "30"))),
            temp_dir=Path(os.getenv("TEMP_DIR", "/tmp/humoo-beo")).resolve(),
        )

    def ensure_temp_dir(self) -> None:
        self.temp_dir.mkdir(parents=True, exist_ok=True)
