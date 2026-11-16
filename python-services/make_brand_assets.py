"""Generate the TraceLess brand assets.

The mark is an "eroding trace": concentric scan rings around a solid record,
with the outermost ring dissolving into dots. It reads as a footprint being
reduced to what actually matters.

Colour mark : cyan inner ring, cool white middle ring, faint cyan outer dots.
Mono mark   : one dark ink colour for light surfaces.
Favicon     : the same mark on a dark tile so it survives a browser tab.
"""

import math
from pathlib import Path

from PIL import Image, ImageDraw

PUBLIC = Path(__file__).resolve().parent.parent / "traceless-app" / "public"
BRAND = PUBLIC / "brand"
BRAND.mkdir(parents=True, exist_ok=True)

CYAN = "#49D7FF"
INK = "#F5F8FC"
DARK = "#0B0F18"


def arc(r: float, sweep_degrees: float) -> str:
    circumference = 2 * math.pi * r
    dash = circumference * (sweep_degrees / 360)
    gap = circumference - dash
    return f"{dash:.2f} {gap:.2f}"


def mark_svg(*, inner: str, middle: str, outer: str, core: str) -> str:
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" role="img" aria-label="TraceLess">
  <g fill="none" stroke-linecap="round">
    <circle cx="24" cy="24" r="9" stroke="{inner}" stroke-width="3.4"
            stroke-dasharray="{arc(9, 288)}" transform="rotate(-72 24 24)"/>
    <circle cx="24" cy="24" r="16" stroke="{middle}" stroke-width="3"
            stroke-dasharray="{arc(16, 232)}" transform="rotate(48 24 24)" opacity="0.85"/>
    <circle cx="24" cy="24" r="21.5" stroke="{outer}" stroke-width="2.4"
            stroke-dasharray="1.4 8.2" transform="rotate(-20 24 24)" opacity="0.65"/>
  </g>
  <circle cx="24" cy="24" r="3" fill="{core}"/>
</svg>
"""


(BRAND / "traceless-mark.svg").write_text(
    mark_svg(inner=CYAN, middle=INK, outer=CYAN, core=INK), encoding="utf-8"
)
(BRAND / "traceless-mark-mono.svg").write_text(
    mark_svg(inner=DARK, middle=DARK, outer=DARK, core=DARK), encoding="utf-8"
)

favicon = f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-label="TraceLess">
  <rect width="32" height="32" rx="7" fill="{DARK}"/>
  <g fill="none" stroke-linecap="round">
    <circle cx="16" cy="16" r="6" stroke="{CYAN}" stroke-width="2.6"
            stroke-dasharray="{arc(6, 288)}" transform="rotate(-72 16 16)"/>
    <circle cx="16" cy="16" r="10.6" stroke="{INK}" stroke-width="2.2"
            stroke-dasharray="{arc(10.6, 232)}" transform="rotate(48 16 16)" opacity="0.85"/>
    <circle cx="16" cy="16" r="13.6" stroke="{CYAN}" stroke-width="1.6"
            stroke-dasharray="1 6.4" transform="rotate(-20 16 16)" opacity="0.6"/>
  </g>
  <circle cx="16" cy="16" r="2" fill="{INK}"/>
</svg>
"""
(PUBLIC / "favicon.svg").write_text(favicon, encoding="utf-8")


def render_tile(size: int) -> Image.Image:
    scale = 8
    dim = size * scale
    image = Image.new("RGBA", (dim, dim), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)

    draw.rounded_rectangle(
        (0, 0, dim - 1, dim - 1), radius=int(dim * 7 / 32), fill=DARK
    )

    centre = dim / 2
    unit = dim / 32

    def ring(radius: float, width: float, colour: str, start: float, end: float):
        stroke = max(1, int(round(width * unit)))
        for step in range(int(start), int(end)):
            draw.arc(
                (
                    centre - radius * unit,
                    centre - radius * unit,
                    centre + radius * unit,
                    centre + radius * unit,
                ),
                start=step,
                end=step + 1,
                fill=colour,
                width=stroke,
            )

    ring(6, 2.3, CYAN, -72, 216)
    ring(10.6, 2.3, INK, 48, 280)

    # dissolving outer ring, drawn as individual dots
    for step in range(26):
        angle = math.radians(step * (360 / 26))
        x = centre + math.cos(angle) * 14.2 * unit
        y = centre + math.sin(angle) * 14.2 * unit
        radius = 0.55 * unit
        draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=CYAN)

    dot_radius = 1.9 * unit
    draw.ellipse(
        (centre - dot_radius, centre - dot_radius, centre + dot_radius, centre + dot_radius),
        fill=INK,
    )

    return image.resize((size, size), Image.LANCZOS)


sizes = [16, 24, 32, 48, 64, 128, 256]
renders = [render_tile(size) for size in sizes]
renders[-1].save(PUBLIC / "apple-touch-icon.png", "PNG")
renders[-1].save(PUBLIC / "favicon.ico", format="ICO", sizes=[(s, s) for s in sizes])

print("brand assets written")
