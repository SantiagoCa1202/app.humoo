from __future__ import annotations

from dataclasses import dataclass

from .classification import PageClassification
from .ingestion import PageContent


@dataclass(slots=True)
class EventOrderSegment:
    source_key: str
    event_order_number: str
    pages: list[PageContent]
    classifications: list[PageClassification]

    @property
    def text(self) -> str:
        return "\n\n".join(page.text for page in self.pages if page.text)


def segment_event_orders(pages: list[PageContent], classifications: list[PageClassification]) -> tuple[list[EventOrderSegment], list[dict[str, object]]]:
    grouped: dict[str, list[tuple[PageContent, PageClassification]]] = {}
    unresolved: list[dict[str, object]] = []
    current_eo: str | None = None
    for page, classification in zip(pages, classifications, strict=True):
        eo = classification.event_order_number
        if classification.page_type == "EVENT_ORDER" and eo:
            current_eo = eo
        elif classification.page_type in {"CONTINUATION", "DIAGRAM", "ATTACHMENT"} and eo is None:
            eo = current_eo
        if eo:
            grouped.setdefault(eo, []).append((page, classification))
        elif page.text:
            unresolved.append({"type": "other", "source_text": page.text[:500], "page_number": page.page_number, "related_source_key": None, "reason": "Page could not be assigned to an EventOrder", "confidence": classification.confidence, "review_recommended": True})

    segments = [
        EventOrderSegment(f"eo:{number}", number, [item[0] for item in entries], [item[1] for item in entries])
        for number, entries in grouped.items()
    ]
    return segments, unresolved
