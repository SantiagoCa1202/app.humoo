from __future__ import annotations

import re
from dataclasses import dataclass

from .ingestion import DocumentContent, PageContent


@dataclass(slots=True)
class PageClassification:
    page_number: int
    page_type: str
    event_order_number: str | None
    confidence: float
    text_available: bool
    ocr_used: bool
    source_text: str
    warnings: list[str]


EO_RE = re.compile(r"(?:EVENT\s+ORDER|EVENT\s+ORDER\s*#|\bEO\s*#?)\s*[:#-]?\s*(\d{3,})", re.IGNORECASE)


def classify_pages(document: DocumentContent) -> list[PageClassification]:
    classifications: list[PageClassification] = []
    previous_eo: str | None = None
    for page in document.pages:
        text = page.text.strip()
        lower = text.lower()
        match = EO_RE.search(text)
        eo = match.group(1) if match else None
        page_type = "UNKNOWN"
        confidence = 0.45 if text else 0.2
        warnings = list(page.warnings)
        if match:
            page_type = "EVENT_ORDER"
            confidence = 0.98
            previous_eo = eo
        elif re.search(r"\b(page\s+\d+\s+of\s+\d+|continued|continuation)\b", lower):
            page_type = "CONTINUATION"
            confidence = 0.82
            eo = previous_eo
        elif re.search(r"\b(floor\s+plan|room\s+diagram|diagram|layout)\b", lower):
            page_type = "DIAGRAM"
            confidence = 0.86
            eo = previous_eo
        elif re.search(r"\b(attachment|attached|appendix)\b", lower):
            page_type = "ATTACHMENT"
            confidence = 0.72
            eo = previous_eo
        else:
            warnings.append("Page could not be classified from explicit document signals")

        classifications.append(PageClassification(page.page_number, page_type, eo, confidence, bool(text), page.ocr_used, text[:1000], warnings))
    return classifications
