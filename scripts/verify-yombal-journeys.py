#!/usr/bin/env python3
from __future__ import annotations

import argparse
import http.cookiejar
import json
import re
import sys
from dataclasses import dataclass
from datetime import datetime
from html import unescape
from pathlib import Path
from typing import Any
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import HTTPCookieProcessor, Request, build_opener


ROOT = Path(__file__).resolve().parents[1]
MATRIX_PATH = ROOT / "wp-content" / "plugins" / "yombal-core" / "resources" / "journeys" / "journey-matrix.json"
DIST_DIR = ROOT / "dist"
USER_AGENT = "Mozilla/5.0 (Yombal Journey Check)"


@dataclass
class ScenarioResult:
    scenario_id: str
    actor: str
    title: str
    url: str
    status: str
    details: str


def clean_text(value: str) -> str:
    value = re.sub(r"<[^>]+>", " ", value)
    value = unescape(value)
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def extract_first_h1(html: str) -> str:
    match = re.search(r"<h1\b[^>]*>(.*?)</h1>", html, re.I | re.S)
    return clean_text(match.group(1)) if match else ""


def extract_canonical(html: str) -> str:
    match = re.search(r"<link[^>]+rel=[\"']canonical[\"'][^>]+href=[\"']([^\"']+)", html, re.I)
    return clean_text(match.group(1)) if match else ""


def extract_robots(html: str) -> list[str]:
    return [
        clean_text(value)
        for value in re.findall(r"<meta[^>]+name=[\"']robots[\"'][^>]+content=[\"']([^\"']+)", html, re.I)
    ]


def build_session():
    cookie_jar = http.cookiejar.CookieJar()
    return build_opener(HTTPCookieProcessor(cookie_jar))


def request(opener, url: str, *, data: dict[str, Any] | None = None) -> tuple[int, str, str]:
    payload = None
    headers = {"User-Agent": USER_AGENT}
    if data is not None:
        payload = urlencode(data).encode("utf-8")
        headers["Content-Type"] = "application/x-www-form-urlencoded"

    req = Request(url, data=payload, headers=headers)
    try:
        with opener.open(req, timeout=45) as response:
            return response.getcode(), response.geturl(), response.read().decode("utf-8", errors="ignore")
    except HTTPError as error:
        body = error.read().decode("utf-8", errors="ignore")
        return error.code, error.geturl(), body


def login(base_url: str, login_name: str, password: str):
    opener = build_session()
    login_url = base_url.rstrip("/") + "/wp-login.php"
    status, final_url, html = request(
        opener,
        login_url,
        data={
            "log": login_name,
            "pwd": password,
            "rememberme": "forever",
            "wp-submit": "Se connecter",
            "redirect_to": base_url.rstrip("/") + "/wp-admin/",
            "testcookie": "1",
        },
    )
    if status >= 400 or "login_error" in html.lower():
        raise RuntimeError(f"Echec de connexion pour {login_name}: {status} -> {final_url}")
    return opener


def deep_get(payload: dict[str, Any], dotted: str) -> Any:
    current: Any = payload
    for part in dotted.split("."):
        if not isinstance(current, dict) or part not in current:
            raise KeyError(dotted)
        current = current[part]
    return current


def resolve_scenario_url(base_url: str, scenario: dict[str, Any], report: dict[str, Any]) -> str:
    if scenario.get("entry_report_key"):
        value = deep_get(report, str(scenario["entry_report_key"]))
        if not isinstance(value, str) or value == "":
            raise KeyError(str(scenario["entry_report_key"]))
        return value

    entry_path = str(scenario.get("entry_path", "")).strip()
    if not entry_path:
        raise KeyError(f"{scenario['id']}: no entry path")

    return base_url.rstrip("/") + entry_path


def load_matrix() -> dict[str, Any]:
    return json.loads(MATRIX_PATH.read_text(encoding="utf-8"))


def fetch_admin_report(base_url: str, admin_user: str, admin_pass: str) -> dict[str, Any]:
    opener = login(base_url, admin_user, admin_pass)
    report_url = base_url.rstrip("/") + "/wp-admin/admin-post.php?action=yombal_export_journey_report"
    status, _, body = request(opener, report_url)
    if status != 200:
        raise RuntimeError(f"Export Journey Lab en echec: HTTP {status}")
    return json.loads(body)


def run_scenario(
    opener,
    base_url: str,
    scenario: dict[str, Any],
    report: dict[str, Any],
) -> ScenarioResult:
    url = resolve_scenario_url(base_url, scenario, report)
    expected_status = int(scenario.get("expected_status", 200))
    validation_mode = str(scenario.get("validation_mode", "http+html"))
    status_code, final_url, body = request(opener, url)
    failures: list[str] = []

    if status_code != expected_status:
        failures.append(f"status {status_code} != {expected_status}")

    expected_blocks = [str(value) for value in scenario.get("expected_blocks", [])]
    forbidden_blocks = [str(value) for value in scenario.get("forbidden_blocks", [])]

    if validation_mode.endswith("json"):
        haystack = body
        for token in expected_blocks:
            if token not in haystack:
                failures.append(f"missing token {token}")
        for token in forbidden_blocks:
            if token in haystack:
                failures.append(f"forbidden token {token}")
    else:
        h1 = extract_first_h1(body)
        canonical = extract_canonical(body)
        robots = extract_robots(body)
        clean_html = clean_text(body)

        expected_h1 = str(scenario.get("expected_h1", "")).strip()
        expected_robots = str(scenario.get("expected_robots", "")).strip()
        expected_canonical_path = str(scenario.get("expected_canonical_path", "")).strip()

        if expected_h1 and h1 != expected_h1:
            failures.append(f"h1 '{h1}' != '{expected_h1}'")
        if expected_robots and expected_robots not in robots:
            failures.append(f"robots {robots} missing {expected_robots}")
        if expected_canonical_path:
            expected_canonical = base_url.rstrip("/") + expected_canonical_path
            if canonical != expected_canonical:
                failures.append(f"canonical {canonical} != {expected_canonical}")

        for token in expected_blocks:
            if token not in clean_html:
                failures.append(f"missing block {token}")
        for token in forbidden_blocks:
            if token in clean_html:
                failures.append(f"forbidden block {token}")

    details = "OK" if not failures else "; ".join(failures)
    return ScenarioResult(
        scenario_id=str(scenario["id"]),
        actor=str(scenario["actor"]),
        title=str(scenario["title"]),
        url=final_url,
        status="validated" if not failures else "failed",
        details=details,
    )


def build_role_sessions(base_url: str, report: dict[str, Any], admin_user: str, admin_pass: str):
    sessions: dict[str, Any] = {"anonymous": build_session()}
    sessions["admin"] = login(base_url, admin_user, admin_pass)

    for role_key in ["client", "tailor", "fabric_vendor", "pending_partner"]:
        role = report.get("users", {}).get(role_key)
        if not isinstance(role, dict):
            continue
        sessions[role_key] = login(base_url, str(role["login"]), str(role["password"]))

    return sessions


def write_reports(results: list[ScenarioResult]) -> tuple[Path, Path]:
    DIST_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    json_path = DIST_DIR / f"journey-coverage-{stamp}.json"
    md_path = DIST_DIR / f"journey-coverage-{stamp}.md"

    payload = [
        {
            "scenario_id": result.scenario_id,
            "actor": result.actor,
            "title": result.title,
            "url": result.url,
            "status": result.status,
            "details": result.details,
        }
        for result in results
    ]
    json_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")

    lines = [
        "# Yombal Journey Coverage",
        "",
        "| Scenario | Acteur | Etat | Details |",
        "| --- | --- | --- | --- |",
    ]
    for result in results:
        lines.append(
            f"| {result.scenario_id} | {result.actor} | {result.status} | {result.details.replace('|', '/')} |"
        )
    md_path.write_text("\n".join(lines) + "\n", encoding="utf-8")
    return json_path, md_path


def main() -> int:
    parser = argparse.ArgumentParser(description="Verify Yombal journeys from the canonical matrix.")
    parser.add_argument("--base-url", default="https://yombal.sn")
    parser.add_argument("--admin-user", required=True)
    parser.add_argument("--admin-pass", required=True)
    args = parser.parse_args()

    matrix = load_matrix()
    report = fetch_admin_report(args.base_url, args.admin_user, args.admin_pass)
    sessions = build_role_sessions(args.base_url, report, args.admin_user, args.admin_pass)

    results: list[ScenarioResult] = []
    for scenario in matrix.get("scenarios", []):
        actor = str(scenario.get("actor", "anonymous"))
        opener = sessions.get(actor)
        if opener is None:
            results.append(
                ScenarioResult(
                    scenario_id=str(scenario["id"]),
                    actor=actor,
                    title=str(scenario["title"]),
                    url="",
                    status="blocked",
                    details=f"session missing for actor {actor}",
                )
            )
            continue

        try:
            results.append(run_scenario(opener, args.base_url, scenario, report))
        except Exception as exc:  # noqa: BLE001
            results.append(
                ScenarioResult(
                    scenario_id=str(scenario["id"]),
                    actor=actor,
                    title=str(scenario["title"]),
                    url="",
                    status="failed",
                    details=str(exc),
                )
            )

    json_path, md_path = write_reports(results)
    failures = [result for result in results if result.status != "validated"]

    print(f"JSON report: {json_path}")
    print(f"Markdown report: {md_path}")

    if failures:
        print("Failing or blocked scenarios:")
        for failure in failures:
            print(f"- {failure.scenario_id}: {failure.details}")
        return 1

    print("All journey scenarios validated.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
