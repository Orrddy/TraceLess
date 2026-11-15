import os
import sys
import json
import re
from typing import List, Dict, Any

class MockScanProvider:
    """
    Simulates external reconnaissance scans against public OSINT data sources
    (such as WhatsMyName, data broker directories, social profiles, public contact info).
    """

    SOURCES = [
        {"name": "GitHub", "category": "developer_profile", "base_url": "https://github.com/{}"},
        {"name": "Twitter / X", "category": "social_profile", "base_url": "https://x.com/{}"},
        {"name": "Gravatar", "category": "public_avatar", "base_url": "https://gravatar.com/{}"},
        {"name": "LinkedIn Public Directory", "category": "professional_directory", "base_url": "https://linkedin.com/in/{}"},
        {"name": "FastPeopleSearch (Index)", "category": "data_broker", "base_url": "https://fastpeoplesearch.com/name/{}"},
        {"name": "Keybase", "category": "cryptographic_identity", "base_url": "https://keybase.io/{}"},
        {"name": "Reddit", "category": "community_profile", "base_url": "https://reddit.com/user/{}"},
        {"name": "Pastebin Public Archives", "category": "public_paste", "base_url": "https://pastebin.com/u/{}"},
        {"name": "Medium", "category": "publishing_profile", "base_url": "https://medium.com/@{}"},
        {"name": "Dev.to", "category": "developer_profile", "base_url": "https://dev.to/{}"}
    ]

    def scan_identifier(self, identifier_type: str, identifier_value: str) -> List[Dict[str, Any]]:
        findings = []
        slug = re.sub(r'[^a-zA-Z0-9_\-\.]', '', identifier_value)
        
        for source in self.SOURCES:
            confidence = 0.88 if source["category"] in ["developer_profile", "social_profile"] else 0.76
            risk = "moderate"
            removal_method = "manual_instructions"
            status = "needs_user_action"

            if source["category"] == "data_broker":
                risk = "high"
                removal_method = "automated_request"
                status = "ready_to_remove"
            elif source["category"] == "public_paste":
                risk = "critical"
                removal_method = "manual_instructions"
                status = "needs_user_action"
            elif source["category"] == "public_avatar":
                risk = "low"
                status = "needs_user_action"

            findings.append({
                "source": source["name"],
                "category": source["category"],
                "identifier_type": identifier_type,
                "identifier": identifier_value,
                "url": source["base_url"].format(slug),
                "confidence": confidence,
                "risk": risk,
                "removal_method": removal_method,
                "status": status,
                "title": f"Public footprint detected on {source['name']}",
                "description": f"Exposed public profile linked to identifier '{identifier_value}' without strict visibility protection.",
                "action_guidance": f"Review privacy settings or execute removal request for {source['name']}."
            })
        return findings

def main():
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Usage: scanner.py <identifier_type> <identifier_value>"}))
        sys.exit(1)

    id_type = sys.argv[1]
    id_val = sys.argv[2]

    scanner = MockScanProvider()
    results = scanner.scan_identifier(id_type, id_val)
    print(json.dumps({"status": "success", "findings": results}, indent=2))

if __name__ == "__main__":
    main()
