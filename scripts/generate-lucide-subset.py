#!/usr/bin/env python3
"""Rebuild the committed Lucide font subset from lucide-static."""

from __future__ import annotations

import argparse
import json
import re
from pathlib import Path

try:
    from fontTools import subset
    from fontTools.ttLib import TTFont
except ImportError as exc:  # pragma: no cover - environment failure
    raise SystemExit("fontTools and brotli are required") from exc


MAP_ENTRY_RE = re.compile(
    r'"([a-z0-9-]+)"\s*:\s*"\\([0-9a-f]{4})"\s*,?\s*//\s*([a-z0-9-]+)',
    re.IGNORECASE,
)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "source_root",
        type=Path,
        help="extracted lucide-static package directory containing font/",
    )
    parser.add_argument(
        "--repo",
        type=Path,
        default=Path(__file__).resolve().parents[1],
    )
    args = parser.parse_args()

    source_font = args.source_root / "font/lucide.woff2"
    source_codepoints = args.source_root / "font/codepoints.json"
    scss_path = args.repo / "plugins/modern_layout/assets/_modern.scss"
    output = args.repo / "plugins/modern_layout/assets/shared/fonts/lucide-subset.woff2"
    for required in (source_font, source_codepoints, scss_path):
        if not required.exists():
            raise SystemExit(f"missing required path: {required}")

    codepoints = json.loads(source_codepoints.read_text(encoding="utf-8"))
    scss = scss_path.read_text(encoding="utf-8")
    start = scss.find("$lucideIcons:")
    end = scss.find("\n);", start)
    if start < 0 or end < 0:
        raise SystemExit("could not find $lucideIcons map")

    requested: set[int] = set()
    entries = MAP_ENTRY_RE.findall(scss[start:end])
    if not entries:
        raise SystemExit("$lucideIcons has no parseable entries")
    for alias, encoded, source_name in entries:
        expected = codepoints.get(source_name)
        actual = int(encoded, 16)
        if expected is None:
            raise SystemExit(f"unknown lucide-static icon in {alias}: {source_name}")
        if actual != expected:
            raise SystemExit(
                f"codepoint drift for {alias}/{source_name}: "
                f"SCSS U+{actual:04X}, package U+{expected:04X}"
            )
        requested.add(actual)

    # The RSS Fontello remap is outside the Glyphicon map but is still served
    # by this font. Keep it explicit so rebuilding cannot remove it silently.
    requested.add(codepoints["rss"])

    options = subset.Options()
    options.flavor = "woff2"
    options.hinting = False
    options.layout_features = ["*"]
    font = TTFont(source_font, recalcTimestamp=False)
    subsetter = subset.Subsetter(options=options)
    subsetter.populate(unicodes=sorted(requested))
    subsetter.subset(font)
    font.flavor = "woff2"
    font.recalcTimestamp = False
    output.parent.mkdir(parents=True, exist_ok=True)
    font.save(output)
    print(f"Wrote {output} with {len(requested)} Lucide glyphs")


if __name__ == "__main__":
    main()
