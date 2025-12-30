#!/usr/bin/env python3
"""
Generate WordPress.org plugin directory graphics for Pressify.

Outputs to: ../.wordpress-org/

Assets created (PNG):
- icon-128x128.png
- icon-256x256.png
- banner-772x250.png
- banner-1544x500.png
- screenshot-1.png
- screenshot-2.png
"""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path


def _require_pillow() -> None:
    try:
        import PIL  # noqa: F401
    except Exception as e:  # pragma: no cover
        raise SystemExit(
            "Pillow is required. Install with: python3 -m pip install --user pillow"
        ) from e


_require_pillow()

from PIL import Image, ImageDraw, ImageFilter, ImageFont  # noqa: E402


ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / ".wordpress-org"

FONT_REGULAR = Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")
FONT_BOLD = Path("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf")


@dataclass(frozen=True)
class Brand:
    name: str = "Pressify"
    tagline1: str = "Shopify products + cart"
    tagline2: str = "for WordPress"
    subtag: str = "No WooCommerce required"
    bg0: tuple[int, int, int] = (11, 19, 32)  # slate-900
    bg1: tuple[int, int, int] = (2, 6, 23)  # slate-950
    fg: tuple[int, int, int] = (248, 250, 252)  # slate-50
    muted: tuple[int, int, int] = (148, 163, 184)  # slate-400
    accent: tuple[int, int, int] = (34, 197, 94)  # green-500


BRAND = Brand()


def font(path: Path, size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    try:
        return ImageFont.truetype(str(path), size=size)
    except Exception:
        return ImageFont.load_default()


def lerp(a: int, b: int, t: float) -> int:
    return int(round(a + (b - a) * t))


def gradient(size: tuple[int, int], c0: tuple[int, int, int], c1: tuple[int, int, int]) -> Image.Image:
    w, h = size
    img = Image.new("RGB", (w, h), c0)
    px = img.load()
    for y in range(h):
        t = y / max(1, h - 1)
        r = lerp(c0[0], c1[0], t)
        g = lerp(c0[1], c1[1], t)
        b = lerp(c0[2], c1[2], t)
        for x in range(w):
            px[x, y] = (r, g, b)
    return img


def rounded_rect(draw: ImageDraw.ImageDraw, xy: tuple[int, int, int, int], radius: int, fill) -> None:
    draw.rounded_rectangle(xy, radius=radius, fill=fill)


def add_noise_dots(img: Image.Image, density: float = 0.003) -> Image.Image:
    # Subtle dotted texture, deterministic (no RNG) based on pixel grid.
    w, h = img.size
    overlay = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(overlay)
    step = max(6, int((1.0 / max(density, 1e-6)) ** 0.5))
    for y in range(0, h, step):
        for x in range(0, w, step):
            if ((x * 131 + y * 977) % 17) == 0:
                d.ellipse((x, y, x + 2, y + 2), fill=(255, 255, 255, 10))
    return Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")


def draw_logo_mark(base: Image.Image, box: tuple[int, int, int, int]) -> None:
    # A rounded square with a "P" letterform.
    x0, y0, x1, y1 = box
    w, h = x1 - x0, y1 - y0
    mark = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(mark)

    radius = int(min(w, h) * 0.22)
    rounded_rect(d, (0, 0, w - 1, h - 1), radius=radius, fill=(*BRAND.accent, 255))

    # Inner highlight
    highlight = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    dh = ImageDraw.Draw(highlight)
    rounded_rect(
        dh,
        (int(w * 0.06), int(h * 0.06), int(w * 0.94), int(h * 0.94)),
        radius=int(radius * 0.85),
        fill=(255, 255, 255, 18),
    )
    highlight = highlight.filter(ImageFilter.GaussianBlur(radius=2))
    mark = Image.alpha_composite(mark, highlight)

    # "P"
    f = font(FONT_BOLD, int(h * 0.62))
    td = ImageDraw.Draw(mark)
    text = "P"
    bbox = td.textbbox((0, 0), text, font=f)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    tx = (w - tw) // 2
    ty = (h - th) // 2 - int(h * 0.02)
    td.text((tx, ty), text, font=f, fill=(255, 255, 255, 245))

    base.paste(mark, (x0, y0), mark)


def make_icon(size: int) -> Image.Image:
    img = gradient((size, size), BRAND.bg0, BRAND.bg1)
    img = add_noise_dots(img, density=0.004)
    pad = int(size * 0.14)
    draw_logo_mark(img, (pad, pad, size - pad, size - pad))
    return img


def make_banner(size: tuple[int, int]) -> Image.Image:
    w, h = size
    img = gradient((w, h), BRAND.bg0, BRAND.bg1)
    img = add_noise_dots(img, density=0.0025)
    d = ImageDraw.Draw(img)

    # Accent ribbon
    ribbon = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    rd = ImageDraw.Draw(ribbon)
    rd.polygon(
        [
            (int(w * 0.62), 0),
            (w, 0),
            (w, int(h * 0.42)),
        ],
        fill=(*BRAND.accent, 45),
    )
    ribbon = ribbon.filter(ImageFilter.GaussianBlur(radius=12))
    img = Image.alpha_composite(img.convert("RGBA"), ribbon).convert("RGB")
    d = ImageDraw.Draw(img)

    # Logo mark
    mark_size = int(h * 0.56)
    mx = int(h * 0.22)
    my = (h - mark_size) // 2
    draw_logo_mark(img, (mx, my, mx + mark_size, my + mark_size))

    # Text
    title_font = font(FONT_BOLD, int(h * 0.28))
    line_font = font(FONT_REGULAR, int(h * 0.12))
    sub_font = font(FONT_REGULAR, int(h * 0.10))

    tx = mx + mark_size + int(h * 0.20)
    ty = my + int(h * 0.02)
    d.text((tx, ty), BRAND.name, font=title_font, fill=BRAND.fg)

    ty2 = ty + int(h * 0.30)
    d.text((tx, ty2), BRAND.tagline1, font=line_font, fill=BRAND.fg)

    ty3 = ty2 + int(h * 0.14)
    d.text((tx, ty3), BRAND.tagline2, font=line_font, fill=BRAND.fg)

    ty4 = ty3 + int(h * 0.15)
    d.text((tx, ty4), BRAND.subtag, font=sub_font, fill=BRAND.muted)

    return img


def make_screenshot(size: tuple[int, int], variant: int) -> Image.Image:
    w, h = size
    img = gradient((w, h), (15, 23, 42), (2, 6, 23))
    img = add_noise_dots(img, density=0.002)
    d = ImageDraw.Draw(img)

    # Header bar
    d.rectangle((0, 0, w, int(h * 0.11)), fill=(15, 23, 42))
    title_font = font(FONT_BOLD, int(h * 0.045))
    d.text((int(w * 0.04), int(h * 0.032)), f"{BRAND.name} — Demo UI", font=title_font, fill=BRAND.fg)

    # Two different screenshots: product grid vs cart
    if variant == 1:
        # Product grid mock
        d.text((int(w * 0.04), int(h * 0.145)), "Product grid (shortcode: [pressify_products])", font=font(FONT_REGULAR, int(h * 0.03)), fill=BRAND.muted)
        card_w = int(w * 0.28)
        card_h = int(h * 0.26)
        gap = int(w * 0.04)
        start_x = int(w * 0.04)
        start_y = int(h * 0.20)
        for row in range(2):
            for col in range(3):
                x0 = start_x + col * (card_w + gap)
                y0 = start_y + row * (card_h + int(h * 0.05))
                x1 = x0 + card_w
                y1 = y0 + card_h
                d.rounded_rectangle((x0, y0, x1, y1), radius=18, fill=(30, 41, 59))
                # image placeholder
                d.rounded_rectangle((x0 + 14, y0 + 14, x1 - 14, y0 + int(card_h * 0.55)), radius=14, fill=(51, 65, 85))
                d.text((x0 + 18, y0 + int(card_h * 0.62)), f"Product {row*3+col+1}", font=font(FONT_BOLD, int(h * 0.028)), fill=BRAND.fg)
                d.text((x0 + 18, y0 + int(card_h * 0.72)), "Variant • $29.00", font=font(FONT_REGULAR, int(h * 0.024)), fill=BRAND.muted)
                # button
                bx0, by0 = x0 + 18, y0 + int(card_h * 0.82)
                bx1, by1 = x0 + int(card_w * 0.55), y0 + int(card_h * 0.95)
                d.rounded_rectangle((bx0, by0, bx1, by1), radius=14, fill=BRAND.accent)
                d.text((bx0 + 14, by0 + 8), "Add to cart", font=font(FONT_BOLD, int(h * 0.022)), fill=(5, 10, 20))
    else:
        # Cart mock
        d.text((int(w * 0.04), int(h * 0.145)), "Cart (shortcode: [pressify_cart])", font=font(FONT_REGULAR, int(h * 0.03)), fill=BRAND.muted)
        panel = (int(w * 0.04), int(h * 0.20), int(w * 0.96), int(h * 0.90))
        d.rounded_rectangle(panel, radius=22, fill=(30, 41, 59))
        px0, py0, px1, py1 = panel
        # line items
        y = py0 + 24
        for i in range(3):
            row = (px0 + 24, y, px1 - 24, y + int(h * 0.14))
            d.rounded_rectangle(row, radius=18, fill=(51, 65, 85))
            # thumb
            d.rounded_rectangle((row[0] + 16, row[1] + 16, row[0] + 16 + int(h * 0.10), row[1] + 16 + int(h * 0.10)), radius=14, fill=(71, 85, 105))
            d.text((row[0] + 16 + int(h * 0.12), row[1] + 20), f"Product {i+1}", font=font(FONT_BOLD, int(h * 0.03)), fill=BRAND.fg)
            d.text((row[0] + 16 + int(h * 0.12), row[1] + 20 + int(h * 0.045)), "Qty 1 • $29.00", font=font(FONT_REGULAR, int(h * 0.026)), fill=BRAND.muted)
            y += int(h * 0.16)
        # checkout button
        cb = (px1 - int(w * 0.26), py1 - int(h * 0.12), px1 - 24, py1 - 24)
        d.rounded_rectangle(cb, radius=20, fill=BRAND.accent)
        d.text((cb[0] + 22, cb[1] + 18), "Checkout", font=font(FONT_BOLD, int(h * 0.032)), fill=(5, 10, 20))

    return img


def save_png(img: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    img.save(path, format="PNG", optimize=True)


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    save_png(make_icon(128), OUT_DIR / "icon-128x128.png")
    save_png(make_icon(256), OUT_DIR / "icon-256x256.png")

    save_png(make_banner((772, 250)), OUT_DIR / "banner-772x250.png")
    save_png(make_banner((1544, 500)), OUT_DIR / "banner-1544x500.png")

    save_png(make_screenshot((1280, 720), variant=1), OUT_DIR / "screenshot-1.png")
    save_png(make_screenshot((1280, 720), variant=2), OUT_DIR / "screenshot-2.png")

    print(f"Wrote assets to: {OUT_DIR}")


if __name__ == "__main__":
    main()

