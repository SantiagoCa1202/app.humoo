from __future__ import annotations

from typing import Any

from ..segmentation import EventOrderSegment
from .marriott_sheraton import parse_event_order as parse_marriott_sheraton


def parse_event_order(segment: EventOrderSegment, *, document_id: str, source_filename: str, profile: str | None = None) -> dict[str, Any]:
    if profile in {None, "generic", "marriott_sheraton"}:
        return parse_marriott_sheraton(segment, document_id=document_id, source_filename=source_filename)
    return parse_marriott_sheraton(segment, document_id=document_id, source_filename=source_filename)
