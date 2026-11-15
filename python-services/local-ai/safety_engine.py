from typing import List, Dict, Any

class LocalAISafetyEngine:
    """
    Enforces privacy-preserving analysis, categorization, risk assessment,
    and automated removal guidance without scraping private data, evading anti-bot,
    or exposing passwords.
    """

    ALLOWED_ACTIONS = [
        "categorize_findings",
        "detect_duplicates",
        "identify_false_positives",
        "summarize_exposure",
        "prioritize_tasks",
        "explain_risks",
        "generate_removal_guidance"
    ]

    PROHIBITED_ACTIVITIES = [
        "bypass_authentication",
        "evade_rate_limits",
        "circumvent_anti_bot",
        "access_private_data",
        "expose_passwords",
        "doxxing",
        "unauthorized_profiling"
    ]

    @classmethod
    def evaluate_risk(cls, findings: List[Dict[str, Any]], has_credential_leaks: bool) -> Dict[str, Any]:
        """
        Calculates 0-100 privacy score and provides clear, reassuring risk explanation.
        """
        score = 100
        critical_count = 0
        high_count = 0
        moderate_count = 0

        for f in findings:
            r = f.get("risk", "moderate")
            if r == "critical":
                score -= 15
                critical_count += 1
            elif r == "high":
                score -= 8
                high_count += 1
            elif r == "moderate":
                score -= 3
                moderate_count += 1
            else:
                score -= 1

        if has_credential_leaks:
            score -= 20

        score = max(5, min(100, score))

        recommendations = []
        if critical_count > 0 or has_credential_leaks:
            recommendations.append("Update passwords across exposed services and activate hardware or app-based 2FA.")
        if high_count > 0:
            recommendations.append("Initiate automated opt-out removals from public directory databases.")
        if not recommendations:
            recommendations.append("Your public profile exposure is within acceptable privacy thresholds.")

        return {
            "privacy_score": score,
            "exposure_level": "High" if score < 60 else ("Moderate" if score < 85 else "Low"),
            "critical_findings": critical_count,
            "high_findings": high_count,
            "moderate_findings": moderate_count,
            "recommendations": recommendations,
            "disclaimer": "This score is an estimate designed to help prioritize privacy actions based on public data."
        }
