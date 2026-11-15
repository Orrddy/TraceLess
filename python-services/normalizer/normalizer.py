import json
from typing import List, Dict, Any

class ResultNormalizer:
    @staticmethod
    def normalize_finding(raw: Dict[str, Any]) -> Dict[str, Any]:
        return {
            "source": raw.get("source", "Unknown"),
            "category": raw.get("category", "other"),
            "identifier_type": raw.get("identifier_type", "username"),
            "identifier": raw.get("identifier", ""),
            "url": raw.get("url", ""),
            "confidence": float(raw.get("confidence", 0.5)),
            "risk": raw.get("risk", "moderate"),
            "removal_method": raw.get("removal_method", "manual_instructions"),
            "status": raw.get("status", "needs_user_action"),
            "title": raw.get("title", "Public Exposure Detected"),
            "description": raw.get("description", ""),
            "action_guidance": raw.get("action_guidance", "Follow removal procedure.")
        }
