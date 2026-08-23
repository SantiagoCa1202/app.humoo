from __future__ import annotations

import hashlib
import re
from typing import Any


def stable_source_key(*parts: object) -> str:
    normalized = "|".join(" ".join(str(part or "").split()).lower() for part in parts)
    return hashlib.sha1(normalized.encode("utf-8")).hexdigest()[:12]


def normalize_name(value: str | None) -> str | None:
    if value is None:
        return None
    value = " ".join(value.replace("**", " ").split()).strip(" -:")
    if not value:
        return None
    replacements = {
        "caluiflower": "Cauliflower",
        "rolls & butter": "Rolls with Butter",
        "assorted rolls & butter": "Assorted Rolls with Butter",
        "macaroni & cheese bites": "Mac & Cheese Bites",
        "mac & cheese bites": "Mac & Cheese Bites",
        "protein and power bars": "Protein and Breakfast Bars",
    }
    return replacements.get(value.lower(), value)


def split_venue_candidates(source: str | None) -> list[str]:
    if not source or not source.strip():
        return []
    value = " ".join(source.split()).strip(" ,")
    match = re.fullmatch(r"(?P<prefix>[A-Za-z][A-Za-z' /-]*?)\s+(?P<numbers>[A-Za-z0-9][A-Za-z0-9 ,&/-]*)", value)
    if match and re.search(r"\d", match.group("numbers")):
        prefix = match.group("prefix").strip()
        tokens = [token for token in re.split(r"\s*(?:,|&|and)\s*", match.group("numbers"), flags=re.IGNORECASE) if token.strip()]
        if len(tokens) > 1:
            return [f"{prefix} {token.strip()}" for token in tokens]
    parts = [part.strip() for part in re.split(r"\s*(?:,|&|\band\b)\s*", value, flags=re.IGNORECASE) if part.strip()]
    return parts or [value]


def parse_quantity(source_text: str) -> dict[str, Any]:
    raw = " ".join(source_text.split())
    ordered_quantity: float | int | None = None
    ordered_unit: str | None = None
    pricing_quantity: float | int | None = None
    pricing_unit: str | None = None
    price: float | None = None
    currency: str | None = None

    leading = re.search(r"^\((?P<quantity>\d+(?:\.\d+)?)\)\s*", raw)
    if leading:
        ordered_quantity = _number(leading.group("quantity"))
    ppl = re.search(r"[- ](?P<quantity>\d+(?:\.\d+)?)\s*ppl\b", raw, re.IGNORECASE)
    if ppl and ordered_quantity is None:
        ordered_quantity = _number(ppl.group("quantity"))
        ordered_unit = "people"
    if ordered_quantity is not None and ordered_unit is None:
        ordered_unit = "each"

    price_match = re.search(r"@\s*\$(?P<price>[\d,.]+)\s*(?:per\s+(?P<unit>[A-Za-z]+)|(?P<each>each))?", raw, re.IGNORECASE)
    if price_match:
        price = float(price_match.group("price").replace(",", ""))
        unit = price_match.group("unit") or ("each" if price_match.group("each") else None)
        pricing_unit = unit.lower() if unit else None
        pricing_quantity = 1 if pricing_unit else None
        currency = "USD"
        if ordered_quantity is not None and pricing_unit and ordered_unit == "each":
            ordered_unit = pricing_unit

    explicit_production = re.search(r"(?:production|produce|make)\s*[:=-]?\s*(\d+(?:\.\d+)?)\s*([A-Za-z]+)?", raw, re.IGNORECASE)
    production_quantity = _number(explicit_production.group(1)) if explicit_production else None
    production_unit = explicit_production.group(2) if explicit_production else None
    return {
        "ordered_quantity": ordered_quantity,
        "ordered_unit": ordered_unit,
        "pricing_quantity": pricing_quantity,
        "pricing_unit": pricing_unit,
        "price": price,
        "currency": currency,
        "production_quantity": production_quantity,
        "production_unit": production_unit,
        "raw_quantity_text": raw,
        "source_text": raw,
    }


def classify_dietary(source: str) -> str:
    lower = source.lower()
    if "allerg" in lower:
        return "allergy"
    if "intoler" in lower:
        return "intolerance"
    if "vegan" in lower:
        return "vegan"
    if "vegetarian" in lower or "vegeterian" in lower:
        return "vegetarian"
    if any(token in lower for token in ("halal", "kosher", "pork", "beef")):
        return "religious" if any(token in lower for token in ("halal", "kosher")) else "preference"
    if any(token in lower for token in ("no ", "without ", "preference")):
        return "preference"
    return "unknown"


def _number(value: str) -> int | float:
    number = float(value)
    return int(number) if number.is_integer() else number
