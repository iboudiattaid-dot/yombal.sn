#!/usr/bin/env python3
from __future__ import annotations

import argparse
import re
import sys
from dataclasses import dataclass
from html import unescape
from typing import Iterable
from urllib.error import HTTPError
from urllib.request import HTTPRedirectHandler, Request, build_opener


USER_AGENT = "Mozilla/5.0 (Yombal Smoke Check)"


@dataclass(frozen=True)
class PageCheck:
    path: str
    expected_status: int = 200
    expected_final: str | None = None
    expected_canonical: str | None = None
    expected_robots: str | None = None
    expected_h1: str | None = None
    forbid_tokens: tuple[str, ...] = ()


@dataclass(frozen=True)
class RedirectCheck:
    path: str
    expected_status: int
    expected_location: str


class NoRedirectHandler(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def fetch(url: str, allow_redirects: bool) -> tuple[int, str, str]:
    opener = build_opener() if allow_redirects else build_opener(NoRedirectHandler)
    request = Request(url, headers={"User-Agent": USER_AGENT})

    try:
        with opener.open(request, timeout=30) as response:
            return response.getcode(), response.geturl(), response.read().decode("utf-8", errors="ignore")
    except HTTPError as error:
        body = error.read().decode("utf-8", errors="ignore")
        return error.code, error.headers.get("Location", error.geturl()), body


def extract_title(html: str) -> str:
    match = re.search(r"<title>(.*?)</title>", html, re.I | re.S)
    return clean_text(match.group(1)) if match else ""


def extract_canonical(html: str) -> str:
    match = re.search(r"<link[^>]+rel=[\"']canonical[\"'][^>]+href=[\"']([^\"']+)", html, re.I)
    return clean_text(match.group(1)) if match else ""


def extract_robots(html: str) -> list[str]:
    return [
        clean_text(value)
        for value in re.findall(r"<meta[^>]+name=[\"']robots[\"'][^>]+content=[\"']([^\"']+)", html, re.I)
    ]


def extract_first_h1(html: str) -> tuple[int, str]:
    matches = re.findall(r"<h1\b[^>]*>(.*?)</h1>", html, re.I | re.S)
    if not matches:
        return 0, ""

    return len(matches), clean_text(matches[0])


def clean_text(value: str) -> str:
    value = re.sub(r"<[^>]+>", " ", value)
    value = unescape(value)
    value = re.sub(r"\s+", " ", value)

    return value.strip()


def run_page_checks(base_url: str, checks: Iterable[PageCheck]) -> list[str]:
    failures: list[str] = []

    for check in checks:
        url = base_url.rstrip("/") + check.path
        status, final_url, html = fetch(url, allow_redirects=True)
        title = extract_title(html)
        canonical = extract_canonical(html)
        robots = extract_robots(html)
        h1_count, first_h1 = extract_first_h1(html)

        print(f"[PAGE] {check.path} status={status} final={final_url}")
        print(f"       title={title}")
        print(f"       canonical={canonical}")
        print(f"       robots={robots}")
        print(f"       h1_count={h1_count} h1={first_h1}")

        if status != check.expected_status:
            failures.append(f"{check.path}: expected status {check.expected_status}, got {status}")

        if check.expected_final and final_url != check.expected_final:
            failures.append(f"{check.path}: expected final URL {check.expected_final}, got {final_url}")

        if check.expected_canonical and canonical != check.expected_canonical:
            failures.append(f"{check.path}: expected canonical {check.expected_canonical}, got {canonical}")

        if check.expected_robots and check.expected_robots not in robots:
            failures.append(f"{check.path}: expected robots {check.expected_robots}, got {robots}")

        if h1_count != 1:
            failures.append(f"{check.path}: expected 1 H1, got {h1_count}")

        if check.expected_h1 and first_h1 != check.expected_h1:
            failures.append(f"{check.path}: expected H1 '{check.expected_h1}', got '{first_h1}'")

        for token in check.forbid_tokens:
            if token in html:
                failures.append(f"{check.path}: found forbidden token '{token}'")

    return failures


def run_redirect_checks(base_url: str, checks: Iterable[RedirectCheck]) -> list[str]:
    failures: list[str] = []

    for check in checks:
        url = base_url.rstrip("/") + check.path
        status, location, _ = fetch(url, allow_redirects=False)
        print(f"[REDIRECT] {check.path} status={status} location={location}")

        if status != check.expected_status:
            failures.append(f"{check.path}: expected redirect status {check.expected_status}, got {status}")

        if location != check.expected_location:
            failures.append(f"{check.path}: expected location {check.expected_location}, got {location}")

    return failures


def main() -> int:
    parser = argparse.ArgumentParser(description="Run production smoke checks for Yombal.")
    parser.add_argument("--base-url", default="https://yombal.sn", help="Base URL to check.")
    args = parser.parse_args()

    base_url = args.base_url.rstrip("/")

    page_checks = [
        PageCheck("/", expected_final=f"{base_url}/", expected_canonical=f"{base_url}/", expected_h1="Votre tenue sur mesure, livrée chez vous au Sénégal", forbid_tokens=("/store-manager/", "/mon-compte/")),
        PageCheck("/catalogue-tailleurs/", expected_final=f"{base_url}/catalogue-tailleurs/", expected_canonical=f"{base_url}/catalogue-tailleurs/", expected_h1="Catalogue des tailleurs Yombal"),
        PageCheck("/catalogue-tissus/", expected_final=f"{base_url}/catalogue-tissus/", expected_canonical=f"{base_url}/catalogue-tissus/", expected_h1="Catalogue tissus Yombal"),
        PageCheck("/catalogue-modeles/", expected_final=f"{base_url}/catalogue-modeles/", expected_canonical=f"{base_url}/catalogue-modeles/", expected_h1="Modeles et creations Yombal"),
        PageCheck("/store/ibrahima_tailleur/", expected_final=f"{base_url}/store/ibrahima_tailleur/", expected_h1="Ibrahima Ndiaye", forbid_tokens=("wp-login.php",)),
        PageCheck("/produit/kaftan-homme-bapteme/", expected_final=f"{base_url}/produit/kaftan-homme-bapteme/", expected_canonical=f"{base_url}/produit/kaftan-homme-bapteme/", expected_h1="Kaftan Homme Baptême"),
        PageCheck("/devenir-partenaire-yombal/", expected_final=f"{base_url}/devenir-partenaire-yombal/", expected_canonical=f"{base_url}/devenir-partenaire-yombal/", expected_h1="Devenir partenaire Yombal"),
        PageCheck("/connexion/", expected_final=f"{base_url}/connexion/", expected_canonical=f"{base_url}/connexion/", expected_robots="noindex, follow", expected_h1="Connectez-vous simplement"),
        PageCheck("/messages-yombal/", expected_final=f"{base_url}/messages-yombal/", expected_robots="noindex, nofollow", expected_h1="Messages Yombal", forbid_tokens=("wp-login.php",)),
        PageCheck("/litiges-yombal/", expected_final=f"{base_url}/litiges-yombal/", expected_robots="noindex, nofollow", expected_h1="Aide et litiges Yombal", forbid_tokens=("wp-login.php",)),
        PageCheck("/espace-client-yombal/", expected_final=f"{base_url}/espace-client-yombal/", expected_robots="noindex, nofollow", expected_h1="Espace client Yombal", forbid_tokens=("wp-login.php",)),
        PageCheck("/espace-partenaire-yombal/", expected_final=f"{base_url}/espace-partenaire-yombal/", expected_robots="noindex, nofollow", expected_h1="Espace partenaire Yombal", forbid_tokens=("wp-login.php",)),
    ]

    redirect_checks = [
        RedirectCheck("/modeles/", 302, f"{base_url}/catalogue-modeles/"),
        RedirectCheck("/litige/", 302, f"{base_url}/litiges-yombal/"),
        RedirectCheck("/mes-messages/", 302, f"{base_url}/messages-yombal/"),
        RedirectCheck("/vendor-register/", 302, f"{base_url}/devenir-partenaire-yombal/"),
        RedirectCheck("/vendor-registration/", 302, f"{base_url}/devenir-partenaire-yombal/"),
        RedirectCheck("/dashboard-partenaire/", 302, f"{base_url}/espace-partenaire-yombal/"),
        RedirectCheck("/connexion-2/", 302, f"{base_url}/connexion/"),
        RedirectCheck("/connexion-3/", 302, f"{base_url}/connexion/"),
    ]

    failures = []
    failures.extend(run_page_checks(base_url, page_checks))
    failures.extend(run_redirect_checks(base_url, redirect_checks))

    if failures:
        print("\nFAILURES:")
        for failure in failures:
            print(f"- {failure}")
        return 1

    print("\nAll smoke checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
