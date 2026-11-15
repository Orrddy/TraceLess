"""Offline checks for the TraceLess scanner worker.

Run with: python -m unittest discover -s python-services/scanner/tests
"""

import json
import os
import sys
import unittest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import worker  # noqa: E402


class StubScanner(worker.ScannerService):
    """Scanner whose HTTP layer is replaced by canned responses."""

    def __init__(self, responses, offline=False):
        super().__init__(offline=offline, delay_ms=0, concurrency=1, site_limit=5)
        self.responses = responses
        self.calls = []

    def _selected_sites(self):
        return [
            {
                "name": "Example",
                "cat": "social",
                "uri_check": "https://example.test/{account}",
                "e_code": 200,
                "e_string": "profile for",
                "m_code": 404,
                "m_string": "no such user",
            }
        ]

    def _fetch(self, url):
        self.calls.append(url)
        if isinstance(self.responses, Exception):
            raise self.responses
        return self.responses


class SlugTests(unittest.TestCase):
    def test_strips_unsafe_characters(self):
        self.assertEqual(worker._slug_identifier(" jo hn/../doe "), "john.doe")

    def test_rejects_empty_and_overlong(self):
        self.assertIsNone(worker._slug_identifier("///"))
        self.assertIsNone(worker._slug_identifier("a" * 65))

    def test_category_mapping(self):
        self.assertEqual(worker._normalize_category("coding"), "developer_profile")
        self.assertEqual(worker._normalize_category("social"), "social_profile")
        self.assertEqual(worker._normalize_category(None), "social_profile")


class RuleTests(unittest.TestCase):
    def setUp(self):
        self.scanner = StubScanner((200, "profile for johndoe", "https://example.test/johndoe"))

    def site(self, **overrides):
        base = {
            "name": "Example",
            "cat": "social",
            "uri_check": "https://example.test/{account}",
            "e_code": 200,
            "e_string": "profile for",
            "m_code": 404,
            "m_string": "no such user",
        }
        base.update(overrides)
        return base

    def test_existence_rule_verifies(self):
        result = self.scanner._check_site(self.site(), "johndoe")
        self.assertEqual(result.outcome, worker.STATUS_FOUND)
        self.assertEqual(result.rule, "e_string")
        self.assertEqual(result.http_status, 200)
        self.assertEqual(len(result.body_sha256), 64)

    def test_missing_profile_is_not_found(self):
        self.scanner.responses = (200, "no such user here", "https://example.test/johndoe")
        result = self.scanner._check_site(self.site(), "johndoe")
        self.assertEqual(result.outcome, worker.STATUS_NOT_FOUND)
        self.assertEqual(result.rule, "m_string")

    def test_blocked_source_is_unknown(self):
        self.scanner.responses = (429, "rate limited", "https://example.test/johndoe")
        result = self.scanner._check_site(self.site(), "johndoe")
        self.assertEqual(result.outcome, worker.STATUS_UNKNOWN)
        self.assertEqual(result.reason, worker.REASON_BLOCKED)

    def test_unmatched_response_is_unknown(self):
        self.scanner.responses = (200, "<html>something else</html>", "https://example.test/johndoe")
        result = self.scanner._check_site(self.site(), "johndoe")
        self.assertEqual(result.outcome, worker.STATUS_UNKNOWN)
        self.assertEqual(result.reason, worker.REASON_RULE_MISMATCH)

    def test_network_error_is_unknown(self):
        self.scanner.responses = worker.urllib.error.URLError("dns")
        result = self.scanner._check_site(self.site(), "johndoe")
        self.assertEqual(result.outcome, worker.STATUS_UNKNOWN)
        self.assertEqual(result.reason, worker.REASON_NETWORK)

    def test_invalid_template_is_unknown(self):
        result = self.scanner._check_site(self.site(uri_check="ftp://example.test/{account}"), "johndoe")
        self.assertEqual(result.outcome, worker.STATUS_UNKNOWN)
        self.assertEqual(result.reason, worker.REASON_INVALID_URL)


class PayloadTests(unittest.TestCase):
    def test_consent_is_required(self):
        payload = StubScanner((200, "x", "https://example.test")).scan("username", "johndoe", False)
        self.assertEqual(payload["status"], "error")

    def test_only_username_is_accepted(self):
        payload = StubScanner((200, "x", "https://example.test")).scan("email", "a@b.test", True)
        self.assertEqual(payload["status"], "error")

    def test_offline_mode_reports_unknown_without_findings(self):
        payload = StubScanner((200, "profile for", "https://example.test"), offline=True).scan(
            "username", "johndoe", True
        )
        self.assertEqual(payload["status"], "success")
        self.assertEqual(payload["findings"], [])
        self.assertTrue(payload["sources"])
        self.assertTrue(all(s["outcome"] == worker.STATUS_UNKNOWN for s in payload["sources"]))
        self.assertTrue(all(s["reason"] == "network_disabled" for s in payload["sources"]))

    def test_verified_findings_carry_evidence(self):
        payload = StubScanner((200, "profile for johndoe", "https://example.test/johndoe")).scan(
            "username", "johndoe", True
        )
        self.assertEqual(payload["status"], "success")
        self.assertTrue(payload["findings"])
        finding = payload["findings"][0]
        self.assertEqual(finding["verification_status"], "verified")
        self.assertEqual(finding["evidence"]["rule"], "e_string")
        self.assertEqual(finding["evidence"]["http_status"], 200)

    def test_payload_is_json_serializable(self):
        payload = StubScanner((200, "profile for", "https://example.test"), offline=True).scan(
            "username", "johndoe", True
        )
        self.assertIsInstance(json.dumps(payload), str)


if __name__ == "__main__":
    unittest.main()
