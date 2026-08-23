from __future__ import annotations

import json
import unittest
from pathlib import Path

from app.contracts import ContractValidationError, validate_job, validate_result
from app.engine import extract_document


class ExtractorContractTests(unittest.TestCase):
    def setUp(self) -> None:
        self.job = {
            "schema_version": "1.0.0",
            "extraction_run_id": "run-test-001",
            "document_id": "doc-test-001",
            "import_batch_id": None,
            "correlation_id": "corr-test-001",
            "document": {
                "filename": "fixture.pdf",
                "mime_type": "application/pdf",
                "sha256": "a" * 64,
                "file_size": None,
                "source_reference": None,
                "provider_hint": "marriott_sheraton",
                "language_hints": ["en"],
            },
            "options": {"use_ocr": False, "include_layout": True, "include_source_trace": True, "parser_profile": "marriott_sheraton"},
            "requested_at": "2026-08-23T12:00:00Z",
        }

    def test_job_to_result_supports_multiple_event_orders_and_pages(self) -> None:
        validate_job(self.job)
        result = extract_document(self.job, page_texts=self._pages())
        validate_result(result)

        self.assertEqual(result["status"], "completed")
        self.assertEqual([order["event_order_number"] for order in result["event_orders"]], ["936970", "936971"])
        self.assertEqual(result["event_orders"][0]["source_pages"], [1, 2])

    def test_multiple_functions_and_attendance_columns_are_independent(self) -> None:
        result = extract_document(self.job, page_texts=self._pages())
        functions = result["event_orders"][0]["functions"]
        self.assertEqual(len(functions), 2)
        attendance = functions[0]["attendance"]
        self.assertEqual((attendance["expected_count"], attendance["guaranteed_count"], attendance["set_count"]), (180, 140, 200))

    def test_quantity_keeps_pricing_semantics_without_inventing_production(self) -> None:
        result = extract_document(self.job, page_texts=self._pages())
        quantity = result["event_orders"][0]["functions"][0]["menu"]["sections"][0]["items"][0]["quantity"]
        self.assertEqual(quantity["raw_quantity_text"], "(3) Brownies @ $56 per Dozen")
        self.assertEqual(quantity["pricing_unit"], "dozen")
        self.assertIsNone(quantity["production_quantity"])

    def test_non_food_function_is_preserved_and_venues_are_split(self) -> None:
        result = extract_document(self.job, page_texts=self._pages())
        meeting = result["event_orders"][0]["functions"][1]
        self.assertEqual(meeting["source_function_name"], "Office Meeting")
        self.assertFalse(meeting["relevance_signals"]["has_food"])
        self.assertEqual([venue["source_name"] for venue in result["event_orders"][0]["functions"][0]["venue_candidates"]], ["Symphony 1", "Symphony 2", "Symphony 3", "Symphony 4"])

    def test_menu_tbd_quantity_dietary_and_source_sections_are_preserved(self) -> None:
        pages = ["""EVENT ORDER 936980
TIME | FUNCTION | LOCATION | EXP | GTD | SET
6:00 PM - 8:00 PM | Dinner | Ballroom | 100 | 90 | 100
MENU: Dinner Menu TBD
DIETARY RESTRICTIONS:
Alex - No beef
3 Vegan
SPECIAL ARRANGEMENTS:
Ask contact before refreshing
ROOM SETUP:
Schoolroom
AUDIO VISUAL:
Wireless microphone
STAFFING:
(3) Bartenders
"""]
        result = extract_document(self.job, page_texts=pages)
        function = result["event_orders"][0]["functions"][0]
        self.assertEqual(function["menu"]["status"], "tbd")
        self.assertEqual(function["dietary_requirements"][0]["category"], "preference")
        self.assertTrue(function["setup"])
        self.assertTrue(function["av"])
        self.assertTrue(function["staffing"])

    def test_revision_and_cross_order_reference_are_source_level(self) -> None:
        result = extract_document(self.job, page_texts=self._pages())
        self.assertEqual(result["event_orders"][1]["revision"]["number"], 2)
        reference = result["event_orders"][0]["references"][0]
        self.assertEqual(reference["target_event_order_number"], "936971")
        self.assertTrue(reference["resolved"])

    def test_diagram_page_is_preserved_without_geometry(self) -> None:
        pages = ["EVENT ORDER 936990\n12:00 PM - 1:00 PM | Meeting | Room 1 | 10 | 10 | 10", "Room diagram\nStage\nBar"]
        result = extract_document(self.job, page_texts=pages)
        self.assertEqual(result["pages"][1]["page_type"], "DIAGRAM")
        self.assertEqual(result["event_orders"][0]["attachments"][0]["type"], "diagram")

    def test_unknown_content_becomes_unresolved_without_canonical_ids(self) -> None:
        pages = ["EVENT ORDER 936990\nUNKNOWN SECTION:\nMystery Item\n"]
        result = extract_document(self.job, page_texts=pages)
        self.assertEqual(result["status"], "partial")
        self.assertTrue(result["unresolved_items"])
        self.assertNotIn("venue_id", json.dumps(result))

    def test_invalid_confidence_is_rejected(self) -> None:
        payload = extract_document(self.job, page_texts=self._pages())
        payload["event_orders"][0]["confidence"] = 1.5
        with self.assertRaises(ContractValidationError):
            validate_result(payload)

    def _pages(self) -> list[str]:
        fixture = Path(__file__).parent / "fixtures" / "standard-pages.txt"
        return fixture.read_text(encoding="utf-8").split("\n--- PAGE BREAK ---\n")


if __name__ == "__main__":
    unittest.main()
