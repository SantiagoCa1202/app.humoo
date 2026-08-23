from __future__ import annotations

import hashlib
from dataclasses import dataclass, field
from pathlib import Path
from typing import Protocol


@dataclass(slots=True)
class PageContent:
    page_number: int
    text: str
    extraction_method: str = "text"
    text_quality: float = 1.0
    ocr_used: bool = False
    layout: dict[str, object] | None = None
    warnings: list[str] = field(default_factory=list)


@dataclass(slots=True)
class DocumentContent:
    source_filename: str
    checksum: str
    file_size: int | None
    pages: list[PageContent]
    text_mode: str
    ocr_used: bool
    warnings: list[str] = field(default_factory=list)


class OcrProvider(Protocol):
    name: str
    version: str | None

    def extract(self, page_number: int, image: object) -> str: ...


class DocumentReader:
    def __init__(self, *, ocr_provider: OcrProvider | None = None, layout_provider: object | None = None) -> None:
        self.ocr_provider = ocr_provider
        self.layout_provider = layout_provider

    def read_pdf(self, path: str | Path) -> DocumentContent:
        source = Path(path)
        raw = source.read_bytes()
        checksum = hashlib.sha256(raw).hexdigest()
        try:
            import fitz
        except ImportError as exc:  # pragma: no cover - dependency is declared in pyproject
            raise RuntimeError("PyMuPDF is required for PDF ingestion") from exc

        pages: list[PageContent] = []
        with fitz.open(stream=raw, filetype="pdf") as pdf:
            for index, page in enumerate(pdf, start=1):
                text = (page.get_text("text") or "").strip()
                layout = {"block_count": len(page.get_text("blocks"))}
                warnings: list[str] = []
                method = "text" if text else "layout"
                quality = min(1.0, max(0.0, len(text) / 160.0)) if text else 0.0
                ocr_used = False
                if not text:
                    warnings.append("No text-native content was extracted")
                    if self.ocr_provider is not None:
                        image = page.get_pixmap(matrix=fitz.Matrix(1.5, 1.5), alpha=False)
                        text = self.ocr_provider.extract(index, image).strip()
                        method = "ocr"
                        ocr_used = bool(text)
                        quality = min(1.0, max(0.0, len(text) / 160.0)) if text else 0.0
                pages.append(PageContent(index, text, method, quality, ocr_used, layout, warnings))

        return self._build_document(source.name, checksum, len(raw), pages)

    def read_text_pages(self, page_texts: list[str], *, source_filename: str = "<memory>", checksum: str | None = None) -> DocumentContent:
        pages = [
            PageContent(index, text.strip(), "text", min(1.0, max(0.0, len(text.strip()) / 160.0)), False, None, [] if text.strip() else ["Empty text page"])
            for index, text in enumerate(page_texts, start=1)
        ]
        raw = "\n\f\n".join(page.text for page in pages).encode("utf-8")
        return self._build_document(source_filename, checksum or hashlib.sha256(raw).hexdigest(), None, pages)

    def _build_document(self, filename: str, checksum: str, file_size: int | None, pages: list[PageContent]) -> DocumentContent:
        has_text = any(page.text for page in pages)
        has_empty = any(not page.text for page in pages)
        text_mode = "mixed" if has_text and has_empty else "text_native" if has_text else "scanned"
        return DocumentContent(filename, checksum, file_size, pages, text_mode, any(page.ocr_used for page in pages))
