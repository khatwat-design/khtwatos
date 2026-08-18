#!/usr/bin/env python3
"""
يبني أيقونات المشغّل من شعار خارج المخزون:
- طبقة أمامية شفافة حول الشعار (Adaptive foreground)
- أيقونة مسطحة: خلفية بيضاء + الشعار الأحمر (legacy + iOS 1024)
"""
from __future__ import annotations

import sys
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "public/images/kharij-brand-icon.png"

MIPMAP_SIZES = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}


def key_dark_background(im: Image.Image, rgb_max: int = 46) -> Image.Image:
    """يحوّل الخلفية الداكنة/السوداء إلى شفاف."""
    im = im.convert("RGBA")
    px = im.load()
    w, h = im.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if r <= rgb_max and g <= rgb_max and b <= rgb_max and a > 200:
                px[x, y] = (0, 0, 0, 0)
    return im


def trim_and_scale_logo(keyed: Image.Image, canvas_size: int, scale: float = 0.78) -> Image.Image:
    bbox = keyed.getbbox()
    if not bbox:
        return Image.new("RGBA", (canvas_size, canvas_size), (0, 0, 0, 0))
    cropped = keyed.crop(bbox)
    target = max(8, int(canvas_size * scale))
    ratio = min(target / cropped.width, target / cropped.height)
    nw = max(1, int(cropped.width * ratio))
    nh = max(1, int(cropped.height * ratio))
    resized = cropped.resize((nw, nh), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (canvas_size, canvas_size), (0, 0, 0, 0))
    ox = (canvas_size - nw) // 2
    oy = (canvas_size - nh) // 2
    canvas.paste(resized, (ox, oy), resized)
    return canvas


def flat_white_icon(foreground_rgba: Image.Image) -> Image.Image:
    """طبقة مسطّحة بيضاء تحت الشعار."""
    w, h = foreground_rgba.size
    base = Image.new("RGB", (w, h), (255, 255, 255))
    base.paste(foreground_rgba, mask=foreground_rgba.split()[3])
    return base


def main() -> int:
    if not SOURCE.is_file():
        print(f"Missing source: {SOURCE}", file=sys.stderr)
        return 1

    raw = Image.open(SOURCE).convert("RGBA")
    keyed = key_dark_background(raw)

    res_dir = ROOT / "android/app/src/main/res"

    for folder, size in MIPMAP_SIZES.items():
        fg = trim_and_scale_logo(keyed, size)
        flat = flat_white_icon(fg)
        out_dir = res_dir / folder
        out_dir.mkdir(parents=True, exist_ok=True)
        flat.save(out_dir / "ic_launcher.png", format="PNG", optimize=True)
        flat.save(out_dir / "ic_launcher_round.png", format="PNG", optimize=True)
        fg.save(out_dir / "ic_launcher_foreground.png", format="PNG", optimize=True)
        print(f"wrote {folder} ({size}px)")

    ios_icon = ROOT / "ios/App/App/Assets.xcassets/AppIcon.appiconset/AppIcon-512@2x.png"
    ios_size = 1024
    fg_ios = trim_and_scale_logo(keyed, ios_size, scale=0.76)
    flat_ios = flat_white_icon(fg_ios)
    ios_icon.parent.mkdir(parents=True, exist_ok=True)
    flat_ios.save(ios_icon, format="PNG", optimize=True)
    print(f"wrote iOS {ios_icon.relative_to(ROOT)} ({ios_size}px)")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
