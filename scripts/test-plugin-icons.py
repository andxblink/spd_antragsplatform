#!/usr/bin/env python3
"""Verify the runtime Glyphicon inventory against the Lucide subset manifest."""

from __future__ import annotations

import argparse
import re
from pathlib import Path

try:
    from fontTools.ttLib import TTFont
except ImportError as exc:  # pragma: no cover - environment failure
    raise SystemExit("fontTools is required") from exc


GLYPHICON_RE = re.compile(r"glyphicon-([a-z0-9-]+)")
MAP_ENTRY_RE = re.compile(r'"([a-z0-9-]+)"\s*:\s*"\\([0-9a-f]{4})"', re.IGNORECASE)
RUNTIME_SUFFIXES = {".html", ".js", ".php", ".ts", ".vue"}
IGNORED_PARTS = {"node_modules", "plugins", "vendor", "web/assets", "web/css"}
PLUGIN_DYNAMIC_ICONS = {
    "cog",
    "file",
    "globe",
    "home",
    "list-alt",
    "log-in",
    "log-out",
    "menu-right",
    "user",
}
FONTELLO_REMAPS = {0xE0E8, 0xE14A}


def runtime_files(root: Path, *, upstream: bool) -> list[Path]:
    files: list[Path] = []
    for path in root.rglob("*"):
        if not path.is_file() or path.suffix not in RUNTIME_SUFFIXES:
            continue
        relative = path.relative_to(root).as_posix()
        if upstream and any(
            relative == ignored or relative.startswith(f"{ignored}/")
            for ignored in IGNORED_PARTS
        ):
            continue
        files.append(path)
    return files


def glyphicon_inventory(paths: list[Path]) -> set[str]:
    inventory: set[str] = set()
    for path in paths:
        inventory.update(
            GLYPHICON_RE.findall(path.read_text(encoding="utf-8", errors="ignore"))
        )
    return inventory


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("upstream", type=Path, help="path to Antragsgruen checkout")
    parser.add_argument("--repo", type=Path, default=Path(__file__).resolve().parents[1])
    args = parser.parse_args()

    scss_path = args.repo / "plugins/modern_layout/assets/_modern.scss"
    font_path = args.repo / "plugins/modern_layout/assets/shared/fonts/lucide-subset.woff2"
    plugin_root = args.repo / "plugins"
    for required in (scss_path, font_path, plugin_root, args.upstream):
        if not required.exists():
            raise SystemExit(f"missing required path: {required}")

    scss = scss_path.read_text(encoding="utf-8")
    start = scss.find("$lucideIcons:")
    end = scss.find("\n);", start)
    if start < 0 or end < 0:
        raise SystemExit("could not find $lucideIcons map")
    entries = MAP_ENTRY_RE.findall(scss[start:end])
    icon_map = {name: int(codepoint, 16) for name, codepoint in entries}
    if len(icon_map) != len(entries):
        raise SystemExit("duplicate name in $lucideIcons")

    requested = glyphicon_inventory(runtime_files(args.upstream, upstream=True))
    requested.update(glyphicon_inventory(runtime_files(plugin_root, upstream=False)))
    requested.update(PLUGIN_DYNAMIC_ICONS)
    missing_classes = sorted(requested - icon_map.keys())
    if missing_classes:
        raise SystemExit("unmapped Glyphicon classes: " + ", ".join(missing_classes))

    font = TTFont(font_path)
    shipped = {
        codepoint
        for table in font["cmap"].tables
        for codepoint in table.cmap
    }
    manifest = set(icon_map.values()) | FONTELLO_REMAPS
    missing_codepoints = sorted(manifest - shipped)
    unexpected_codepoints = sorted(shipped - manifest)
    if missing_codepoints:
        raise SystemExit(
            "Lucide subset is missing: "
            + ", ".join(f"U+{codepoint:04X}" for codepoint in missing_codepoints)
        )
    if unexpected_codepoints:
        raise SystemExit(
            "Lucide subset has undeclared glyphs: "
            + ", ".join(f"U+{codepoint:04X}" for codepoint in unexpected_codepoints)
        )

    print(
        f"Icon contract passed: {len(requested)} requested classes, "
        f"{len(icon_map)} mapped names, {len(shipped)} shipped Lucide glyphs."
    )
    print("Legacy exception: Fontello Twitter and Facebook brand glyphs.")


if __name__ == "__main__":
    main()
