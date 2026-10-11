#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
SCSS="$ROOT_DIR/plugins/modern_layout/assets/_modern.scss"
CSS="$ROOT_DIR/plugins/spd_layout/assets/layout-spd_layout.css"
HOOKS="$ROOT_DIR/plugins/modern_layout/LayoutHooks.php"

grep -q '\$railBreakpoint: 768px;' "$SCSS"
! grep -q 'modernNoRail' "$SCSS" "$CSS" "$HOOKS"
grep -q 'modernTableView' "$SCSS" "$CSS" "$HOOKS"
grep -q "\$out .= '<span class=\"modernTableView\" hidden></span>';" "$HOOKS"
grep -q "\$out .= '<nav class=\"modernRail\"" "$HOOKS"

# The dense table may shape its content, but it must not hide the persistent
# rail or zero the shell padding at the tablet breakpoint.
! grep -q 'body:has(\.modernTableView) \.modernRail{display:none}' "$CSS"
! grep -q 'body:has(\.modernTableView){padding-left:0}' "$CSS"
grep -Eq '@media screen and \(max-width:[[:space:]]*767px\)' "$CSS"
grep -q 'flex-wrap:[[:space:]]*wrap' "$SCSS"
grep -q 'hyphens: auto' "$SCSS"
grep -q 'hyphens:auto' "$CSS"

echo 'responsive shell contract: ok'
