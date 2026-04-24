from __future__ import annotations

import argparse
import json
import math
import mimetypes
import random
import re
import textwrap
import time
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import Iterable

import requests
from PIL import Image, ImageColor, ImageDraw, ImageFilter, ImageOps


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_ROOT = ROOT / "output" / "media-seed"
MODELS_DIR = OUTPUT_ROOT / "models"
FABRICS_DIR = OUTPUT_ROOT / "fabrics"
LOGOS_DIR = OUTPUT_ROOT / "logos"
MANIFEST_JSON = OUTPUT_ROOT / "manifest.json"
CODE_REFERENCE_MD = OUTPUT_ROOT / "CODE-REFERENCE.md"

SIZE = (1200, 1200)


@dataclass
class AssetSpec:
    code: str
    category: str
    title: str
    slug: str
    usage: str
    palette: list[str]
    uploaded_url: str = ""
    attachment_id: str = ""

    @property
    def filename(self) -> str:
        return f"{self.code.lower()}_{self.slug}.png"

    @property
    def relative_path(self) -> Path:
        folder = {
            "model": MODELS_DIR,
            "fabric": FABRICS_DIR,
            "logo": LOGOS_DIR,
        }[self.category]
        return folder / self.filename


MODEL_SPECS = [
    ("YMB-MDL-01", "Boubou ceremonie saphir", "boubou-ceremonie-saphir", "Hero modele premium"),
    ("YMB-MDL-02", "Kaftan ivoire lagune", "kaftan-ivoire-lagune", "Carte modele ceremonie"),
    ("YMB-MDL-03", "Ensemble Tabaski terracotta", "ensemble-tabaski-terracotta", "Carte collection festive"),
    ("YMB-MDL-04", "Robe bazin lumiere", "robe-bazin-lumiere", "Carte modele femme"),
    ("YMB-MDL-05", "Grand boubou indigo", "grand-boubou-indigo", "Hero catalogue modeles"),
    ("YMB-MDL-06", "Set moderne Dakar", "set-moderne-dakar", "Carte mode urbaine"),
    ("YMB-MDL-07", "Tenue enfant safran", "tenue-enfant-safran", "Carte modele enfant"),
    ("YMB-MDL-08", "Kaftan nuit poudree", "kaftan-nuit-poudree", "Mise en avant soiree"),
    ("YMB-MDL-09", "Boubou femme eden", "boubou-femme-eden", "Carte modele editorial"),
    ("YMB-MDL-10", "Tunique homme atlas", "tunique-homme-atlas", "Carte produit homme"),
    ("YMB-MDL-11", "Tenue mariage perle", "tenue-mariage-perle", "Collection mariage"),
    ("YMB-MDL-12", "Ensemble festif azur", "ensemble-festif-azur", "Bloc inspiration"),
    ("YMB-MDL-13", "Robe couture baobab", "robe-couture-baobab", "Carte couture signature"),
    ("YMB-MDL-14", "Set casual sable", "set-casual-sable", "Modele quotidien"),
    ("YMB-MDL-15", "Boubou royal turquoise", "boubou-royal-turquoise", "Hero boutique"),
    ("YMB-MDL-16", "Caftan minuit or", "caftan-minuit-or", "Carte premium"),
    ("YMB-MDL-17", "Ensemble broderie corail", "ensemble-broderie-corail", "Bloc collection"),
    ("YMB-MDL-18", "Tenue soiree ivoire", "tenue-soiree-ivoire", "Carte evenement"),
    ("YMB-MDL-19", "Robe pagne rivage", "robe-pagne-rivage", "Inspiration femme"),
    ("YMB-MDL-20", "Boubou heritage emeraude", "boubou-heritage-emeraude", "Mise en avant heritage"),
]

FABRIC_SPECS = [
    ("YMB-FAB-01", "Wax soleil Dakar", "wax-soleil-dakar", "Fallback tissu wax"),
    ("YMB-FAB-02", "Bazin lagune royale", "bazin-lagune-royale", "Hero tissus premium"),
    ("YMB-FAB-03", "Bogolan sable noir", "bogolan-sable-noir", "Texture bogolan"),
    ("YMB-FAB-04", "Indigo ondes sine", "indigo-ondes-sine", "Texture indigo"),
    ("YMB-FAB-05", "Damask ivoire brume", "damask-ivoire-brume", "Carte damask"),
    ("YMB-FAB-06", "Wax corail baobab", "wax-corail-baobab", "Carte wax lumineuse"),
    ("YMB-FAB-07", "Bazin vert palmeraie", "bazin-vert-palmeraie", "Texture bazin"),
    ("YMB-FAB-08", "Kente lumiere ambre", "kente-lumiere-ambre", "Mise en avant kente"),
    ("YMB-FAB-09", "Wax turquoise medina", "wax-turquoise-medina", "Carte tissu moderne"),
    ("YMB-FAB-10", "Bogolan terre rouge", "bogolan-terre-rouge", "Texture artisanale"),
    ("YMB-FAB-11", "Brocade nuit etoilee", "brocade-nuit-etoilee", "Carte brocade"),
    ("YMB-FAB-12", "Pagne floral safran", "pagne-floral-safran", "Texture florale"),
    ("YMB-FAB-13", "Damask perle bleue", "damask-perle-bleue", "Fond fiches tissus"),
    ("YMB-FAB-14", "Wax aube fuchsia", "wax-aube-fuchsia", "Accent catalogue"),
    ("YMB-FAB-15", "Bazin royal azur", "bazin-royal-azur", "Texture bazin hero"),
    ("YMB-FAB-16", "Tissu geometrique emeraude", "tissu-geometrique-emeraude", "Pattern geometrique"),
    ("YMB-FAB-17", "Coton artisanal terre", "coton-artisanal-terre", "Texture coton"),
    ("YMB-FAB-18", "Wax minuit or", "wax-minuit-or", "Texture profonde"),
    ("YMB-FAB-19", "Bazin mangue brulee", "bazin-mangue-brulee", "Variation chaude"),
    ("YMB-FAB-20", "Pagne rivage ivoire", "pagne-rivage-ivoire", "Texture claire"),
]

LOGO_SPECS = [
    ("YMB-LGO-01", "Tailleur aiguille saphir", "tailleur-aiguille-saphir", "Logo tailleur"),
    ("YMB-LGO-02", "Tailleur ciseaux ambre", "tailleur-ciseaux-ambre", "Logo tailleur"),
    ("YMB-LGO-03", "Tailleur fil royal", "tailleur-fil-royal", "Logo tailleur"),
    ("YMB-LGO-04", "Tailleur manchette ivoire", "tailleur-manchette-ivoire", "Logo tailleur"),
    ("YMB-LGO-05", "Tailleur bobine teal", "tailleur-bobine-teal", "Logo tailleur"),
    ("YMB-LGO-06", "Tailleur aiguille corail", "tailleur-aiguille-corail", "Logo tailleur"),
    ("YMB-LGO-07", "Tailleur bouton minuit", "tailleur-bouton-minuit", "Logo tailleur"),
    ("YMB-LGO-08", "Tailleur epingle or", "tailleur-epingle-or", "Logo tailleur"),
    ("YMB-LGO-09", "Tailleur monogramme lagune", "tailleur-monogramme-lagune", "Logo tailleur"),
    ("YMB-LGO-10", "Tailleur couture baobab", "tailleur-couture-baobab", "Logo tailleur"),
    ("YMB-LGO-11", "Vendeur tissu soleil", "vendeur-tissu-soleil", "Logo vendeur tissus"),
    ("YMB-LGO-12", "Vendeur rouleau azur", "vendeur-rouleau-azur", "Logo vendeur tissus"),
    ("YMB-LGO-13", "Vendeur etoffe ambre", "vendeur-etoffe-ambre", "Logo vendeur tissus"),
    ("YMB-LGO-14", "Vendeur trame ivoire", "vendeur-trame-ivoire", "Logo vendeur tissus"),
    ("YMB-LGO-15", "Vendeur motif royal", "vendeur-motif-royal", "Logo vendeur tissus"),
    ("YMB-LGO-16", "Vendeur bolt emeraude", "vendeur-bolt-emeraude", "Logo vendeur tissus"),
    ("YMB-LGO-17", "Vendeur wax corail", "vendeur-wax-corail", "Logo vendeur tissus"),
    ("YMB-LGO-18", "Vendeur brocade minuit", "vendeur-brocade-minuit", "Logo vendeur tissus"),
    ("YMB-LGO-19", "Vendeur kente or", "vendeur-kente-or", "Logo vendeur tissus"),
    ("YMB-LGO-20", "Vendeur textile lagune", "vendeur-textile-lagune", "Logo vendeur tissus"),
]

PALETTES = [
    ["#0d2454", "#1f4d9f", "#18c2d4", "#f0a534", "#f6efe4"],
    ["#081e3c", "#164481", "#18a1be", "#f6bf63", "#edf4fa"],
    ["#10264d", "#1d5ca8", "#2db7b5", "#ffb347", "#f9f5ef"],
    ["#14213d", "#275dad", "#0ea5b7", "#ff9f43", "#fff3e0"],
    ["#0e2a47", "#2463b5", "#12b6c8", "#ffb35c", "#f2ede5"],
]


def ensure_dirs() -> None:
    for path in (MODELS_DIR, FABRICS_DIR, LOGOS_DIR):
        path.mkdir(parents=True, exist_ok=True)


def palette_for(code: str) -> list[str]:
    return PALETTES[sum(ord(ch) for ch in code) % len(PALETTES)]


def lerp_color(a: str, b: str, t: float) -> tuple[int, int, int]:
    c1 = ImageColor.getrgb(a)
    c2 = ImageColor.getrgb(b)
    return tuple(int(c1[i] + (c2[i] - c1[i]) * t) for i in range(3))


def gradient_canvas(size: tuple[int, int], colors: list[str], horizontal: bool = False) -> Image.Image:
    primary = Image.linear_gradient("L").resize(size)
    secondary = Image.linear_gradient("L").rotate(90, expand=True).resize(size)
    if horizontal:
        primary, secondary = secondary, primary

    base_a = ImageOps.colorize(primary, colors[0], colors[1]).convert("RGBA")
    base_b = ImageOps.colorize(secondary, colors[1], colors[2]).convert("RGBA")

    return Image.blend(base_a, base_b, 0.32)


def soft_glow(layer: Image.Image, xy: tuple[int, int], radius: int, color: str, alpha: int) -> None:
    overlay = Image.new("RGBA", layer.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    x, y = xy
    draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=ImageColor.getrgb(color) + (alpha,))
    overlay = overlay.filter(ImageFilter.GaussianBlur(radius // 2))
    layer.alpha_composite(overlay)


def hex_rgba(color: str, alpha: int) -> tuple[int, int, int, int]:
    return ImageColor.getrgb(color) + (alpha,)


def draw_model_asset(spec: AssetSpec) -> None:
    rng = random.Random(spec.code)
    colors = spec.palette
    img = gradient_canvas(SIZE, [colors[4], colors[2], colors[1]])
    overlay = Image.new("RGBA", SIZE, (0, 0, 0, 0))

    for _ in range(8):
        soft_glow(
            overlay,
            (rng.randint(80, 1520), rng.randint(80, 1520)),
            rng.randint(120, 280),
            rng.choice(colors[:4]),
            rng.randint(32, 76),
        )

    draw = ImageDraw.Draw(overlay)
    cx = 800 + rng.randint(-80, 80)
    y_base = 1180 + rng.randint(-20, 40)
    torso_top = 420 + rng.randint(-40, 40)
    shoulder_w = 240 + rng.randint(-20, 30)
    hem_w = 510 + rng.randint(-40, 70)
    waist_w = 180 + rng.randint(-10, 20)
    head_r = 88

    draw.ellipse((cx - head_r, torso_top - 170, cx + head_r, torso_top + 6), fill=hex_rgba(colors[4], 220))
    draw.rectangle((cx - 30, torso_top - 10, cx + 30, torso_top + 70), fill=hex_rgba(colors[4], 180))

    garment = [
        (cx - shoulder_w, torso_top + 40),
        (cx + shoulder_w, torso_top + 40),
        (cx + waist_w, torso_top + 320),
        (cx + hem_w, y_base),
        (cx - hem_w, y_base),
        (cx - waist_w, torso_top + 320),
    ]
    draw.polygon(garment, fill=hex_rgba(colors[0], 228))

    inner = [
        (cx - shoulder_w + 56, torso_top + 90),
        (cx + shoulder_w - 56, torso_top + 90),
        (cx + waist_w - 20, torso_top + 308),
        (cx + hem_w - 70, y_base - 24),
        (cx - hem_w + 70, y_base - 24),
        (cx - waist_w + 20, torso_top + 308),
    ]
    draw.polygon(inner, fill=hex_rgba(colors[1], 215))

    for index in range(14):
        offset = int((index - 6.5) * 62)
        draw.line(
            [(cx + offset, torso_top + 110), (cx + offset + rng.randint(-40, 40), y_base - 32)],
            fill=hex_rgba(colors[3], 68 if index % 2 else 92),
            width=10 if index % 2 else 6,
        )

    for index in range(7):
        y = torso_top + 160 + index * 110
        draw.arc(
            (cx - hem_w + 90, y - 36, cx + hem_w - 90, y + 36),
            start=0,
            end=180,
            fill=hex_rgba(colors[4], 76),
            width=6,
        )

    draw.line((cx - shoulder_w - 30, torso_top + 80, cx - hem_w + 50, y_base - 130), fill=hex_rgba(colors[4], 180), width=26)
    draw.line((cx + shoulder_w + 30, torso_top + 80, cx + hem_w - 50, y_base - 130), fill=hex_rgba(colors[4], 180), width=26)

    accent_box = (150, 160, 520, 410)
    draw.rounded_rectangle(accent_box, radius=42, fill=hex_rgba(colors[0], 150), outline=hex_rgba(colors[4], 86), width=3)
    for index in range(5):
        x0 = accent_box[0] + 48 + index * 62
        draw.ellipse((x0, accent_box[1] + 58, x0 + 34, accent_box[1] + 92), fill=hex_rgba(colors[3], 120))
        draw.rectangle((x0 + 12, accent_box[1] + 94, x0 + 22, accent_box[1] + 160), fill=hex_rgba(colors[4], 90))

    overlay = overlay.filter(ImageFilter.GaussianBlur(0.3))
    img.alpha_composite(overlay)
    img.convert("RGB").save(spec.relative_path, quality=94)


def draw_fabric_asset(spec: AssetSpec) -> None:
    rng = random.Random(spec.code)
    colors = spec.palette
    img = gradient_canvas(SIZE, [colors[0], colors[1], colors[2]], horizontal=True)
    overlay = Image.new("RGBA", SIZE, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    motif = sum(ord(ch) for ch in spec.code) % 5
    tile = 220

    for y in range(-tile, SIZE[1] + tile, tile):
        for x in range(-tile, SIZE[0] + tile, tile):
            ox = x + rng.randint(-18, 18)
            oy = y + rng.randint(-18, 18)
            if motif == 0:
                draw.polygon(
                    [(ox + 110, oy + 20), (ox + 200, oy + 110), (ox + 110, oy + 200), (ox + 20, oy + 110)],
                    fill=hex_rgba(colors[3], 110),
                    outline=hex_rgba(colors[4], 105),
                    width=5,
                )
            elif motif == 1:
                draw.ellipse((ox + 36, oy + 36, ox + 184, oy + 184), outline=hex_rgba(colors[4], 110), width=8)
                draw.ellipse((ox + 72, oy + 72, ox + 148, oy + 148), fill=hex_rgba(colors[3], 110))
            elif motif == 2:
                for shift in range(4):
                    draw.arc((ox + 18, oy + shift * 38, ox + 202, oy + 140 + shift * 38), 0, 180, fill=hex_rgba(colors[4], 95), width=6)
            elif motif == 3:
                draw.rounded_rectangle((ox + 28, oy + 28, ox + 192, oy + 192), radius=38, outline=hex_rgba(colors[4], 120), width=5)
                draw.line((ox + 42, oy + 110, ox + 178, oy + 110), fill=hex_rgba(colors[3], 100), width=10)
                draw.line((ox + 110, oy + 42, ox + 110, oy + 178), fill=hex_rgba(colors[3], 100), width=10)
            else:
                for step in range(5):
                    inset = 26 + step * 20
                    draw.rectangle((ox + inset, oy + inset, ox + 220 - inset, oy + 220 - inset), outline=hex_rgba(colors[(step + 1) % 4], 100), width=4)

    for _ in range(90):
        x = rng.randint(0, SIZE[0])
        y = rng.randint(0, SIZE[1])
        r = rng.randint(6, 22)
        draw.ellipse((x - r, y - r, x + r, y + r), fill=hex_rgba(colors[4], rng.randint(22, 52)))

    overlay = overlay.filter(ImageFilter.GaussianBlur(1.6))
    img.alpha_composite(overlay)
    img.convert("RGB").save(spec.relative_path, quality=94)


def draw_logo_asset(spec: AssetSpec) -> None:
    rng = random.Random(spec.code)
    colors = spec.palette
    img = gradient_canvas(SIZE, [colors[0], colors[1], colors[0]])
    overlay = Image.new("RGBA", SIZE, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)

    soft_glow(overlay, (800, 720), 360, colors[3], 80)
    soft_glow(overlay, (640, 980), 260, colors[2], 64)

    draw.ellipse((340, 280, 1260, 1200), fill=hex_rgba("#07172f", 220), outline=hex_rgba(colors[4], 90), width=8)
    draw.ellipse((430, 370, 1170, 1110), fill=hex_rgba(colors[0], 255))
    draw.ellipse((470, 410, 1130, 1070), outline=hex_rgba(colors[4], 105), width=5)

    kind = "tailor" if "tailleur" in spec.slug else "vendor"
    center = (800, 740)

    if kind == "tailor":
        if rng.randint(0, 1) == 0:
            draw.line((560, 930, 930, 560), fill=hex_rgba(colors[4], 230), width=28)
            draw.line((650, 1010, 1010, 650), fill=hex_rgba(colors[3], 210), width=18)
            draw.ellipse((890, 520, 1040, 670), outline=hex_rgba(colors[4], 220), width=18)
            draw.ellipse((760, 650, 910, 800), outline=hex_rgba(colors[3], 220), width=18)
        else:
            draw.arc((540, 520, 1060, 1040), start=220, end=40, fill=hex_rgba(colors[4], 220), width=36)
            draw.line((770, 500, 880, 1010), fill=hex_rgba(colors[3], 215), width=18)
            draw.ellipse((730, 430, 920, 620), outline=hex_rgba(colors[4], 200), width=12)
    else:
        draw.rounded_rectangle((540, 560, 1060, 920), radius=84, outline=hex_rgba(colors[4], 225), width=18, fill=hex_rgba(colors[1], 180))
        draw.line((640, 640, 960, 640), fill=hex_rgba(colors[3], 205), width=18)
        draw.line((640, 760, 960, 760), fill=hex_rgba(colors[3], 205), width=18)
        draw.line((640, 880, 960, 880), fill=hex_rgba(colors[3], 205), width=18)
        draw.polygon([(1060, 720), (1200, 640), (1200, 840)], fill=hex_rgba(colors[4], 220))

    for ring in range(3):
        inset = 150 + ring * 70
        draw.arc((340 + inset, 280 + inset, 1260 - inset, 1200 - inset), start=25 + ring * 18, end=135 + ring * 20, fill=hex_rgba(colors[2], 58), width=5)

    overlay = overlay.filter(ImageFilter.GaussianBlur(0.2))
    img.alpha_composite(overlay)
    img.convert("RGB").save(spec.relative_path, quality=94)


def build_specs() -> list[AssetSpec]:
    specs: list[AssetSpec] = []
    for code, title, slug, usage in MODEL_SPECS:
        specs.append(AssetSpec(code=code, category="model", title=title, slug=slug, usage=usage, palette=palette_for(code)))
    for code, title, slug, usage in FABRIC_SPECS:
        specs.append(AssetSpec(code=code, category="fabric", title=title, slug=slug, usage=usage, palette=palette_for(code)))
    for code, title, slug, usage in LOGO_SPECS:
        specs.append(AssetSpec(code=code, category="logo", title=title, slug=slug, usage=usage, palette=palette_for(code)))
    return specs


def write_manifest(specs: Iterable[AssetSpec]) -> None:
    payload = [asdict(spec) | {"filename": spec.filename, "path": str(spec.relative_path)} for spec in specs]
    MANIFEST_JSON.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")

    grouped = {"model": [], "fabric": [], "logo": []}
    for spec in specs:
        grouped[spec.category].append(spec)

    lines = [
        "# Yombal Media Seed",
        "",
        "## Codes",
        "",
    ]
    for category, title in [("model", "Modèles"), ("fabric", "Tissus"), ("logo", "Logos partenaires")]:
        lines.append(f"### {title}")
        lines.append("")
        for spec in grouped[category]:
            line = f"- `{spec.code}`: {spec.title} | fichier `{spec.filename}` | usage `{spec.usage}`"
            if spec.uploaded_url:
                line += f" | url `{spec.uploaded_url}`"
            lines.append(line)
        lines.append("")

    CODE_REFERENCE_MD.write_text("\n".join(lines), encoding="utf-8")


def generate_assets(specs: list[AssetSpec]) -> None:
    ensure_dirs()
    for spec in specs:
        if spec.category == "model":
            draw_model_asset(spec)
        elif spec.category == "fabric":
            draw_fabric_asset(spec)
        else:
            draw_logo_asset(spec)
    write_manifest(specs)


def existing_media_entry(code: str) -> tuple[str, str]:
    response = requests.get(
        "https://yombal.sn/wp-json/wp/v2/media",
        params={
            "search": code.lower(),
            "per_page": 20,
            "_fields": "id,slug,source_url,title",
        },
        timeout=30,
    )
    response.raise_for_status()
    items = response.json()
    if not items:
        return "", ""

    target = next(
        (
            item
            for item in items
            if code.lower() in str(item.get("slug", "")).lower()
            or code.lower() in str((item.get("title") or {}).get("rendered", "")).lower()
        ),
        items[0],
    )
    return str(target.get("id", "")), str(target.get("source_url", ""))


def admin_session(username: str, password: str) -> requests.Session:
    session = requests.Session()
    session.get("https://yombal.sn/wp-login.php", timeout=30)
    session.post(
        "https://yombal.sn/wp-login.php",
        data={
            "log": username,
            "pwd": password,
            "wp-submit": "Se connecter",
            "redirect_to": "https://yombal.sn/wp-admin/",
            "testcookie": "1",
        },
        timeout=30,
    )
    return session


def upload_nonce(session: requests.Session) -> tuple[str, str]:
    page = session.get("https://yombal.sn/wp-admin/media-new.php", timeout=30)
    page.raise_for_status()
    nonce_match = re.search(r'name="_wpnonce" value="([^"]+)"', page.text)
    referer_match = re.search(r'name="_wp_http_referer" value="([^"]+)"', page.text)
    if not nonce_match or not referer_match:
        raise RuntimeError("Impossible de récupérer le nonce d'upload WordPress.")
    return nonce_match.group(1), referer_match.group(1)


def upload_assets(specs: list[AssetSpec], username: str, password: str, endpoint: str) -> None:
    del endpoint
    session = admin_session(username, password)
    nonce, referer = upload_nonce(session)

    for spec in specs:
        existing_id, existing_url = existing_media_entry(spec.code)
        if existing_url:
            spec.attachment_id = existing_id
            spec.uploaded_url = existing_url
            continue

        path = spec.relative_path
        mime_type = mimetypes.guess_type(path.name)[0] or "image/png"
        with path.open("rb") as handle:
            response = session.post(
                "https://yombal.sn/wp-admin/media-new.php",
                data={
                    "html-upload": "Téléverser",
                    "post_id": "0",
                    "_wpnonce": nonce,
                    "_wp_http_referer": referer,
                },
                files={"async-upload": (path.name, handle, mime_type)},
                timeout=120,
            )
        response.raise_for_status()

        attachment_id = ""
        uploaded_url = ""
        for _ in range(8):
            attachment_id, uploaded_url = existing_media_entry(spec.code)
            if uploaded_url:
                break
            time.sleep(1.2)

        spec.attachment_id = attachment_id
        spec.uploaded_url = uploaded_url

    write_manifest(specs)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--mode", choices=["generate", "upload", "all"], default="all")
    parser.add_argument("--username", default="")
    parser.add_argument("--password", default="")
    parser.add_argument("--endpoint", default="https://yombal.sn/xmlrpc.php")
    args = parser.parse_args()

    specs = build_specs()

    if args.mode in {"generate", "all"}:
        generate_assets(specs)

    if args.mode in {"upload", "all"}:
        if not args.username or not args.password:
            raise SystemExit("--username et --password sont requis pour l'upload.")
        if not MANIFEST_JSON.exists():
            generate_assets(specs)
        upload_assets(specs, args.username, args.password, args.endpoint)


if __name__ == "__main__":
    main()
