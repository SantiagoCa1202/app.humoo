from __future__ import annotations


def build_relevance_signals(text: str, *, has_food: bool, has_dietary: bool, has_setup: bool, has_av: bool, has_staffing: bool, has_service: bool) -> dict[str, object]:
    lower = text.lower()
    has_beverage = any(token in lower for token in ("beverage", "coffee", "bar", "water station", "beer", "wine"))
    production = has_food and any(token in lower for token in ("menu", "buffet", "plated", "food", "dessert", "entrée", "entree"))
    categories: list[str] = []
    if has_food:
        categories.append("food")
    if has_beverage:
        categories.append("beverage")
    if has_service:
        categories.append("service")
    if has_setup:
        categories.append("setup")
    if has_av:
        categories.append("av")
    if has_staffing:
        categories.append("staffing")
    if not categories:
        categories.append("meeting")
    return {
        "has_food": has_food,
        "has_beverage": has_beverage,
        "has_kitchen_production_signal": production,
        "has_dietary_requirements": has_dietary,
        "has_service_requirements": has_service,
        "has_setup": has_setup,
        "has_av": has_av,
        "has_staffing": has_staffing,
        "suggested_categories": categories,
    }
