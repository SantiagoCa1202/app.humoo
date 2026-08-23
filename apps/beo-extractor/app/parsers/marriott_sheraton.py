from __future__ import annotations

import re
from typing import Any

from ..normalization import classify_dietary, normalize_name, parse_quantity, split_venue_candidates, stable_source_key
from ..relevance import build_relevance_signals
from ..segmentation import EventOrderSegment


TIME_RANGE_RE = re.compile(r"^(?P<start>\d{1,2}:\d{2}\s*(?:AM|PM)?)\s*(?:-|–|to)\s*(?P<end>\d{1,2}:\d{2}\s*(?:AM|PM)?)\b", re.IGNORECASE)
EO_META_RE = re.compile(r"(?:EVENT\s+ORDER|\bEO)\s*#?\s*[:#-]?\s*(\d{3,})", re.IGNORECASE)


def parse_event_order(segment: EventOrderSegment, *, document_id: str, source_filename: str) -> dict[str, Any]:
    text = segment.text
    trace = _trace(document_id, segment, text[:800], _confidence(segment))
    revision = _parse_revision(text, document_id, segment)
    functions = _parse_functions(segment, document_id, source_filename)
    references = _parse_references(text, document_id, segment)
    attachments = [_diagram_attachment(document_id, source_filename, page) for page in segment.classifications if page.page_type in {"DIAGRAM", "ATTACHMENT"}]
    return {
        "source_key": segment.source_key,
        "event_order_number": segment.event_order_number,
        "quote_number": _first_group(text, r"(?:QUOTE|QUOTE\s*#)\s*[:#-]?\s*([A-Za-z0-9-]+)"),
        "folio_number": _first_group(text, r"(?:FOLIO|FOLIO\s*#)\s*[:#-]?\s*([A-Za-z0-9-]+)"),
        "organization": _first_group(text, r"(?:ORGANIZATION|ORGANISATION)\s*[:=-]\s*(.+)") or _first_group(text, r"(?:CLIENT|GROUP)\s*[:=-]\s*(.+)"),
        "program_name": _first_group(text, r"(?:PROGRAM|EVENT NAME|EVENT)\s*[:=-]\s*(.+)"),
        "event_date": _first_group(text, r"(?:EVENT DATE|DATE)\s*[:=-]\s*([A-Za-z0-9, /-]+)"),
        "property_name": _first_group(text, r"(?:PROPERTY|HOTEL)\s*[:=-]\s*(.+)"),
        "location_text": _first_group(text, r"(?:LOCATION|ROOM|VENUE)\s*[:=-]\s*(.+)"),
        "revision": revision,
        "source_pages": sorted({page.page_number for page in segment.pages}),
        "functions": functions,
        "references": references,
        "attachments": attachments,
        "source_trace": trace,
        "confidence": _confidence(segment),
    }


def _parse_functions(segment: EventOrderSegment, document_id: str, source_filename: str) -> list[dict[str, Any]]:
    lines = [(page.page_number, line.strip()) for page in segment.pages for line in page.text.splitlines() if line.strip()]
    candidates: list[tuple[int, str, str | None, str | None, str, int]] = []
    attendance_header = any("EXP" in line.upper() and "GTD" in line.upper() and "SET" in line.upper() for _, line in lines)
    for index, (page_number, line) in enumerate(lines):
        match = TIME_RANGE_RE.match(line)
        if not match:
            if re.match(r"^(?:FUNCTION|EVENT FUNCTION)\s*[:=-]", line, re.IGNORECASE):
                name = re.split(r"[:=-]", line, maxsplit=1)[1].strip()
                candidates.append((page_number, name, None, None, line, index))
            continue
        remainder = line[match.end():].strip(" |-")
        start, end = _normalize_time(match.group("start")), _normalize_time(match.group("end"))
        name, location, attendance = _parse_row(remainder, attendance_header)
        candidates.append((page_number, name, location, attendance, line, index))

    if not candidates:
        for line_index, (page_number, line) in enumerate(lines):
            match = re.match(r"^(?:FUNCTION|EVENT FUNCTION)\s*[:=-]\s*(.+)$", line, re.IGNORECASE)
            if match:
                candidates.append((page_number, match.group(1).strip(), None, None, line, line_index))

    functions: list[dict[str, Any]] = []
    for index, (page_number, name, location, attendance_row, source_line, line_index) in enumerate(candidates, start=1):
        context = _function_context(lines, line_index)
        attendance = _attendance(attendance_row or context, document_id, segment, page_number)
        menu = _menu(context, document_id, segment, page_number)
        dietary = _dietary(context, document_id, segment, page_number)
        setup = _source_items(context, "ROOM SETUP", document_id, segment, page_number)
        av = _source_items(context, "AUDIO VISUAL", document_id, segment, page_number)
        staffing = _source_items(context, "STAFFING", document_id, segment, page_number)
        instructions = _instructions(context, document_id, segment, page_number)
        venue_text = location or _location_from_context(context)
        venues = [_venue(value, document_id, segment, page_number) for value in split_venue_candidates(venue_text)]
        has_food = bool(menu["status"] in {"available", "partial", "tbd"} or re.search(r"\b(food|meal|lunch|dinner|breakfast|dessert|buffet|plated)\b", context, re.IGNORECASE))
        has_service = bool(instructions or re.search(r"\b(service|serve|refresh|passed|displayed)\b", context, re.IGNORECASE))
        relevance = build_relevance_signals(context, has_food=has_food, has_dietary=bool(dietary), has_setup=bool(setup), has_av=bool(av), has_staffing=bool(staffing), has_service=has_service)
        source_key = f"eo:{segment.event_order_number}:function:{stable_source_key(name, attendance.get('expected_count'), venue_text, index)}"
        functions.append({
            "source_key": source_key,
            "source_function_name": normalize_name(name) or "Unknown",
            "normalized_type": _normalized_type(name),
            "post_as": _first_group(context, r"POST\s+AS\s*[:=-]\s*([^\n|]+)"),
            "start_time": _time_from_line(source_line, 1),
            "end_time": _time_from_line(source_line, 2),
            "start_datetime": None,
            "end_datetime": None,
            "source_location_text": venue_text,
            "venue_candidates": venues,
            "attendance": attendance,
            "menu": menu,
            "dietary_requirements": dietary,
            "operational_instructions": instructions,
            "staffing": staffing,
            "setup": setup,
            "av": av,
            "attachments": [],
            "relevance_signals": relevance,
            "source_trace": _trace(document_id, segment, source_line, _confidence(segment, context)),
            "confidence": _confidence(segment, context),
        })
    return functions


def _parse_row(remainder: str, attendance_header: bool) -> tuple[str, str | None, str | None]:
    parts = [part.strip() for part in remainder.split("|")]
    if len(parts) >= 3:
        name = parts[0]
        location = parts[1] or None
        row = " | ".join(parts[2:])
        if attendance_header and len(parts) >= 5:
            row = f"EXP {parts[-3]} GTD {parts[-2]} SET {parts[-1]}"
        return name, location, row
    labels = re.search(r"\b(?:EXP|EXPECTED)\s*[:=-]?\s*\d+.*", remainder, re.IGNORECASE)
    row = labels.group(0) if labels else remainder
    before = remainder[:labels.start()].strip(" |-") if labels else remainder
    location_match = re.search(r"\s+(?:at|@)\s+(.+)$", before, re.IGNORECASE)
    if location_match:
        return before[:location_match.start()].strip(), location_match.group(1).strip(), row
    return before, None, row


def _function_context(lines: list[tuple[int, str]], index: int) -> str:
    current_page, current_line = lines[index]
    end = len(lines)
    for next_index in range(index + 1, len(lines)):
        if TIME_RANGE_RE.match(lines[next_index][1]) or re.match(r"^(?:FUNCTION|EVENT FUNCTION)\s*[:=-]", lines[next_index][1], re.IGNORECASE):
            end = next_index
            break
    return "\n".join(line for _, line in lines[index:end])


def _attendance(raw: str, document_id: str, segment: EventOrderSegment, page_number: int) -> dict[str, Any]:
    return {
        "expected_count": _int_label(raw, "EXP|EXPECTED"),
        "guaranteed_count": _int_label(raw, "GTD|GUARANTEED"),
        "set_count": _int_label(raw, "SET"),
        "confidence": 0.94 if re.search(r"\bEXP\b.*\bGTD\b.*\bSET\b", raw, re.IGNORECASE) else 0.72,
        "source_trace": _trace(document_id, segment, raw[:500], 0.94 if raw else 0.5),
    }


def _menu(raw: str, document_id: str, segment: EventOrderSegment, page_number: int) -> dict[str, Any]:
    lines = [line.strip() for line in raw.splitlines() if line.strip()]
    lower = raw.lower()
    trace = _trace(document_id, segment, _first_matching(lines, r"menu|food|breakfast|lunch|dinner|dessert") or raw[:500], 0.88)
    if re.search(r"menu[^\n]*tbd|tbd[^\n]*menu|menu to be determined", raw, re.IGNORECASE):
        return {"status": "tbd", "source_title": "Menu TBD", "sections": [], "raw_text": _first_matching(lines, r"menu.*tbd|tbd.*menu") or "Menu TBD", "confidence": 0.98, "source_trace": trace}
    if re.search(r"no food|no beverage|without food", raw, re.IGNORECASE) and not re.search(r"\b(menu|buffet|plated|dessert)\b", raw, re.IGNORECASE):
        return {"status": "none", "source_title": None, "sections": [], "raw_text": None, "confidence": 0.95, "source_trace": trace}
    menu_lines = _menu_lines(lines)
    sections: list[dict[str, Any]] = []
    current: dict[str, Any] | None = None
    for line in menu_lines:
        if _is_section_title(line):
            current = {"source_title": line.rstrip(":").strip(), "normalized_type": normalize_name(line.rstrip(":").strip()), "service_role": None, "course_type": _course_type(line), "items": [], "confidence": 0.85, "source_trace": _trace(document_id, segment, line, 0.85)}
            sections.append(current)
            continue
        if current is None:
            current = {"source_title": "Items", "normalized_type": "items", "service_role": None, "course_type": None, "items": [], "confidence": 0.68, "source_trace": _trace(document_id, segment, line, 0.68)}
            sections.append(current)
        item = normalize_name(_strip_item_prefix(line))
        if not item or _is_noise(line):
            continue
        quantity = parse_quantity(line)
        current["items"].append({"source_text": line, "source_name": item, "normalized_name": item, "notes": None, "quantity": quantity, "confidence": 0.82, "source_trace": _trace(document_id, segment, line, 0.82)})
    if not sections:
        return {"status": "unknown" if "menu" in lower else "none", "source_title": None, "sections": [], "raw_text": None, "confidence": 0.55, "source_trace": trace}
    status = "partial" if any("tbd" in line.lower() or "pending" in line.lower() for line in menu_lines) else "available"
    return {"status": status, "source_title": sections[0]["source_title"], "sections": sections, "raw_text": "\n".join(menu_lines), "confidence": 0.86, "source_trace": trace}


def _dietary(raw: str, document_id: str, segment: EventOrderSegment, page_number: int) -> list[dict[str, Any]]:
    items: list[dict[str, Any]] = []
    for line in raw.splitlines():
        clean = line.strip(" -|\t")
        if not clean or _is_noise(clean) or re.match(r"^(?:DIETARY|SPECIAL DIETARY)", clean, re.IGNORECASE):
            continue
        count_match = re.fullmatch(r"\(?([0-9]+)\)?\s+(.+)", clean)
        guest_match = re.match(r"^(.+?)\s+-\s+(.+)$", clean)
        guest = None
        count = None
        restriction = None
        if count_match and re.search(r"vegan|vegetarian|allergy|intoler|halal|kosher|gluten|dairy|pork|beef", count_match.group(2), re.IGNORECASE):
            count, restriction = int(count_match.group(1)), count_match.group(2).strip()
        elif guest_match and re.search(r"allergy|intoler|vegan|vegetarian|halal|kosher|gluten|dairy|no |without |pork|beef", guest_match.group(2), re.IGNORECASE):
            guest, restriction = guest_match.group(1).strip(), guest_match.group(2).strip()
        elif re.fullmatch(r"(?:halal|kosher|vegetarian|vegan|no pork|no beef)", clean, re.IGNORECASE):
            restriction = clean
        if restriction:
            items.append({"guest_name": guest, "count": count, "source_restriction": restriction, "normalized_restriction": normalize_name(restriction).lower().replace(" ", "_"), "category": classify_dietary(restriction), "confidence": 0.84, "source_trace": _trace(document_id, segment, clean, 0.84)})
    return items


def _instructions(raw: str, document_id: str, segment: EventOrderSegment, page_number: int) -> list[dict[str, Any]]:
    lines = _section_lines(raw, ("SPECIAL ARRANGEMENTS", "NOTES", "INSTRUCTIONS", "ORDER OF EVENTS"))
    return [{"category": _instruction_category(line), "source_text": line, "normalized_text": normalize_name(line).lower() if normalize_name(line) else None, "confidence": 0.78, "source_trace": _trace(document_id, segment, line, 0.78)} for line in lines if not _is_noise(line)]


def _source_items(raw: str, header: str, document_id: str, segment: EventOrderSegment, page_number: int) -> list[dict[str, Any]]:
    lines = _section_lines(raw, (header,))
    return [{"source_text": line, "confidence": 0.82, "source_trace": _trace(document_id, segment, line, 0.82)} for line in lines if not _is_noise(line)]


def _parse_references(raw: str, document_id: str, segment: EventOrderSegment) -> list[dict[str, Any]]:
    references: list[dict[str, Any]] = []
    for match in re.finditer(r"\b(?:EO|EVENT\s+ORDER)\s*#?\s*(\d{3,})", raw, re.IGNORECASE):
        target = match.group(1)
        if target == segment.event_order_number:
            continue
        source_text = raw[max(0, match.start() - 30):min(len(raw), match.end() + 30)].strip()
        references.append({"source_event_order_number": segment.event_order_number, "source_function_key": None, "target_event_order_number": target, "reference_type": "related_event_order", "source_text": source_text, "confidence": 0.82, "source_trace": _trace(document_id, segment, source_text, 0.82), "resolved": False})
    return references


def _diagram_attachment(document_id: str, source_filename: str, page: Any) -> dict[str, Any]:
    return {"type": "diagram", "page_number": page.page_number, "source_document": source_filename, "labels": [], "extracted_text": page.source_text or None, "related_function_source_key": None, "source_location_text": None, "confidence": page.confidence, "source_trace": {"document_id": document_id, "page_numbers": [page.page_number], "source_text": page.source_text or "diagram", "confidence": page.confidence, "extraction_method": "layout"}}


def _trace(document_id: str, segment: EventOrderSegment, text: str, confidence: float) -> dict[str, Any]:
    pages = sorted({page.page_number for page in segment.pages}) or [1]
    return {"document_id": document_id, "page_numbers": pages, "source_text": text.strip() or "source unavailable", "confidence": max(0.0, min(1.0, confidence)), "extraction_method": "text"}


def _parse_revision(raw: str, document_id: str, segment: EventOrderSegment) -> dict[str, Any]:
    match = re.search(r"\b(REVISED\s*(?:X|x)?\s*(\d+)?|REVISION\s*(\d+)?|POP[- ]?UP)\b", raw, re.IGNORECASE)
    if not match:
        return {"kind": "original", "number": 0, "raw_label": None, "is_revised": False, "confidence": 0.72, "source_trace": _trace(document_id, segment, "No revision label detected", 0.72)}
    raw_label = match.group(1)
    number = int(match.group(2) or match.group(3) or 1) if not re.search("POP", raw_label, re.IGNORECASE) else None
    kind = "popup" if re.search("POP", raw_label, re.IGNORECASE) else "revision"
    return {"kind": kind, "number": number, "raw_label": raw_label, "is_revised": True, "confidence": 0.96, "source_trace": _trace(document_id, segment, raw_label, 0.96)}


def _attendance_int(value: str, label: str) -> int | None:
    match = re.search(rf"\b(?:{label})\b\s*[:=-]?\s*(\d+)", value, re.IGNORECASE)
    return int(match.group(1)) if match else None


def _int_label(value: str, label: str) -> int | None:
    return _attendance_int(value, label)


def _venue(value: str, document_id: str, segment: EventOrderSegment, page_number: int) -> dict[str, Any]:
    return {"source_name": value, "normalized_name": normalize_name(value), "confidence": 0.82, "source_trace": _trace(document_id, segment, value, 0.82)}


def _location_from_context(raw: str) -> str | None:
    return _first_group(raw, r"(?:LOCATION|ROOM|VENUE)\s*[:=-]\s*([^\n|]+)")


def _normalized_type(name: str) -> str | None:
    lower = name.lower()
    for token, normalized in (("breakfast", "breakfast"), ("lunch", "lunch"), ("dinner", "dinner"), ("reception", "reception"), ("meeting", "meeting"), ("office", "meeting"), ("break", "break"), ("registration", "registration"), ("setup", "setup")):
        if token in lower:
            return normalized
    return None


def _normalize_time(value: str) -> str:
    return " ".join(value.upper().replace(".", "").split())


def _time_from_line(line: str, group: int) -> str | None:
    match = TIME_RANGE_RE.match(line)
    return _normalize_time(match.group("start" if group == 1 else "end")) if match else None


def _first_group(value: str, pattern: str) -> str | None:
    match = re.search(pattern, value, re.IGNORECASE | re.MULTILINE)
    return " ".join(match.group(1).split()).strip() if match and match.group(1).strip() else None


def _first_matching(lines: list[str], pattern: str) -> str | None:
    return next((line for line in lines if re.search(pattern, line, re.IGNORECASE)), None)


def _confidence(segment: EventOrderSegment, context: str | None = None) -> float:
    base = min(classification.confidence for classification in segment.classifications) if segment.classifications else 0.6
    return round(max(0.0, min(1.0, base if context is None else base * (0.96 if context else 0.7))), 3)


def _menu_lines(lines: list[str]) -> list[str]:
    start = next((index for index, line in enumerate(lines) if re.search(r"^(?:MENU|FOOD|BEVERAGE)\b|menu\s*:", line, re.IGNORECASE)), None)
    if start is None:
        return [line for line in lines if re.search(r"\b(?:buffet|plated|dessert|salad|entrée|entree|brownie|coffee|breakfast)\b", line, re.IGNORECASE)]
    result: list[str] = []
    for line in lines[start + 1:]:
        if re.match(r"^(?:ROOM SETUP|AUDIO VISUAL|STAFFING|SPECIAL ARRANGEMENTS|NOTES|ATTENDANCE)\b", line, re.IGNORECASE):
            break
        if not re.match(r"^(?:MENU|FOOD|BEVERAGE)\b", line, re.IGNORECASE):
            result.append(line)
    return result


def _is_section_title(line: str) -> bool:
    return bool(re.fullmatch(r"[A-Za-z][A-Za-z &'/-]{2,60}:?", line)) and (line.endswith(":") or line.lower() in {"breakfast", "lunch", "dinner", "dessert", "salad", "entree", "entrée", "beverages", "passed", "displayed", "stations"})


def _course_type(line: str) -> str | None:
    lower = line.lower()
    return next((value for value in ("breakfast", "salad", "entree", "dessert", "beverage", "snack") if value in lower), None)


def _strip_item_prefix(line: str) -> str:
    return re.sub(r"^[-•*]\s*", "", line).strip()


def _section_lines(raw: str, headers: tuple[str, ...]) -> list[str]:
    lines = [line.strip() for line in raw.splitlines()]
    active = False
    result: list[str] = []
    for line in lines:
        if any(re.match(rf"^{re.escape(header)}\b", line, re.IGNORECASE) for header in headers):
            active = True
            remainder = re.sub(r"^[^:]+:", "", line).strip()
            if remainder:
                result.append(remainder)
            continue
        if active and re.match(r"^(?:ROOM SETUP|AUDIO VISUAL|STAFFING|SPECIAL ARRANGEMENTS|NOTES|ATTENDANCE|FOOD|MENU)\b", line, re.IGNORECASE):
            break
        if active and line:
            result.append(line)
    return result


def _instruction_category(line: str) -> str:
    lower = line.lower()
    if any(token in lower for token in ("serve", "refresh", "vip")):
        return "service"
    if any(token in lower for token in ("door", "locked", "security")):
        return "security"
    if any(token in lower for token in ("temperature", "before", "after", "at ")):
        return "timing"
    return "general"


def _is_noise(line: str) -> bool:
    lower = line.lower()
    return lower.startswith(("event order", "quote:", "folio:", "property:", "date:", "organization:", "client:")) or lower in {"menu", "food", "beverage"}
