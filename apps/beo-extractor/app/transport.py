from __future__ import annotations

import json
from dataclasses import dataclass
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import quote
from urllib.request import Request, urlopen


@dataclass
class WorkerHttpError(RuntimeError):
    message: str
    status_code: int | None = None
    retryable: bool = True

    def __str__(self) -> str:
        return self.message


class ExtractionApiClient:
    def __init__(self, base_url: str, worker_id: str, worker_token: str, timeout: float = 30.0) -> None:
        self.base_url = base_url.rstrip("/")
        self.worker_id = worker_id
        self.worker_token = worker_token
        self.timeout = timeout

    def claim(self) -> dict[str, Any] | None:
        payload = self._request_json("POST", "/internal/extraction-jobs/claim", {})
        return payload.get("data")

    def heartbeat(self, run_id: str) -> dict[str, Any]:
        return self._request_json("POST", f"/internal/extraction-jobs/{quote(run_id)}/heartbeat", {})

    def download_document(self, run_id: str) -> bytes:
        return self._request_bytes("GET", f"/internal/extraction-jobs/{quote(run_id)}/document")

    def submit_result(self, run_id: str, result: dict[str, Any]) -> dict[str, Any]:
        return self._request_json("POST", f"/internal/extraction-jobs/{quote(run_id)}/result", {"result": result})

    def submit_failure(self, run_id: str, code: str, message: str, retryable: bool) -> dict[str, Any]:
        return self._request_json(
            "POST",
            f"/internal/extraction-jobs/{quote(run_id)}/failure",
            {"code": code, "message": message[:1000], "retryable": retryable},
        )

    def _request_json(self, method: str, path: str, payload: dict[str, Any]) -> dict[str, Any]:
        body = json.dumps(payload).encode("utf-8")
        response = self._request(method, path, body, "application/json")
        try:
            decoded = json.loads(response.decode("utf-8"))
        except json.JSONDecodeError as exc:
            raise WorkerHttpError("Laravel returned invalid JSON.", retryable=False) from exc
        if not isinstance(decoded, dict):
            raise WorkerHttpError("Laravel returned an invalid response envelope.", retryable=False)
        return decoded

    def _request_bytes(self, method: str, path: str) -> bytes:
        return self._request(method, path, None, None)

    def _request(self, method: str, path: str, body: bytes | None, content_type: str | None) -> bytes:
        headers = {
            "Accept": "application/json, application/pdf",
            "Authorization": f"Bearer {self.worker_token}",
            "X-Worker-ID": self.worker_id,
            "User-Agent": "humoo-beo-extractor-worker/0.1",
        }
        if content_type:
            headers["Content-Type"] = content_type
        request = Request(f"{self.base_url}{path}", data=body, headers=headers, method=method)
        try:
            with urlopen(request, timeout=self.timeout) as response:
                return response.read()
        except HTTPError as exc:
            retryable = exc.code in {408, 425, 429} or exc.code >= 500
            raise WorkerHttpError(
                f"Laravel worker endpoint returned HTTP {exc.code}.",
                status_code=exc.code,
                retryable=retryable,
            ) from exc
        except (TimeoutError, URLError) as exc:
            raise WorkerHttpError("Laravel worker endpoint is unavailable.", retryable=True) from exc
