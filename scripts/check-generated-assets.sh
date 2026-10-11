#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
UPSTREAM=${ANTRAGSGRUEN_ASSET_REFERENCE:-"$ROOT_DIR/temp/audit-upstream-v4.17.1"}
# Every compiled layout stylesheet, as plugin-relative paths without .scss.
LAYOUTS=(
  modern_layout/assets/layout-modern_layout
  spd_layout/assets/layout-spd_layout
)
# The compiler is pinned here, not taken from the reference's node_modules: a
# reference installed without its lockfile once carried a newer Sass than it
# pinned, and the colour serialisation changed between 1.101.0 and 1.101.5.
# 1.105.1 is what upstream main pins (pnpm-lock.yaml) and reproduces the
# committed CSS. Change it only together with a recompile.
SASS_VERSION=${SASS_VERSION:-1.105.1}
# reproduce: byte-identical to the committed CSS (the production-pinned
# release). compile: only prove the source still compiles against a newer
# upstream, whose partials legitimately produce different output.
CSS_CHECK_MODE=${CSS_CHECK_MODE:-reproduce}
[[ "$CSS_CHECK_MODE" == reproduce || "$CSS_CHECK_MODE" == compile ]] ||
  { echo "unknown CSS_CHECK_MODE: $CSS_CHECK_MODE" >&2; exit 2; }

fail() {
  printf 'generated asset check failed: %s\n' "$1" >&2
  exit 1
}

[[ -e "$UPSTREAM/.git" ]] || fail "upstream reference is unavailable: $UPSTREAM"
command -v npx >/dev/null || fail 'npx is required to run the pinned Dart Sass'
for layout in "${LAYOUTS[@]}"; do
  [[ -f "$ROOT_DIR/plugins/$layout.scss" ]] || fail "SCSS source is missing: $layout.scss"
  [[ -f "$ROOT_DIR/plugins/$layout.css" ]] || fail "checked-in CSS is missing: $layout.css"
done

workspace=$(mktemp -d "${TMPDIR:-/tmp}/antragsgruen-plugin-css.XXXXXX")
cleanup() {
  rm -rf "$workspace"
}
trap cleanup EXIT

git -C "$UPSTREAM" archive --format=tar HEAD -o "$workspace/source.tar"
tar -xf "$workspace/source.tar" -C "$workspace"
mkdir -p "$workspace/plugins"
cp -R "$ROOT_DIR/plugins/modern_layout" "$workspace/plugins/modern_layout"
cp -R "$ROOT_DIR/plugins/spd_layout" "$workspace/plugins/spd_layout"

for layout in "${LAYOUTS[@]}"; do
  if ! npx --yes "sass@$SASS_VERSION" \
      --load-path="$workspace/web" \
      --style=compressed \
      --no-source-map \
      "$workspace/plugins/$layout.scss" \
      "$workspace/generated.css" \
      > /dev/null 2> "$workspace/sass.log"; then
    cat "$workspace/sass.log" >&2
    fail "Dart Sass could not compile $layout.scss"
  fi

  if [[ "$CSS_CHECK_MODE" == compile ]]; then
    printf 'plugin CSS compiles: %s passed (%s, sass %s)\n' "$layout" "${UPSTREAM#$ROOT_DIR/}" "$SASS_VERSION"
    continue
  fi

  if ! cmp -s "$ROOT_DIR/plugins/$layout.css" "$workspace/generated.css"; then
    printf 'checked-in CSS: ' >&2
    sha256sum "$ROOT_DIR/plugins/$layout.css" >&2
    printf 'generated CSS:   ' >&2
    sha256sum "$workspace/generated.css" >&2
    fail "$layout.css is not reproducible with Dart Sass $SASS_VERSION"
  fi
  printf 'generated asset reproducibility: %s passed (%s, sass %s)\n' "$layout" "${UPSTREAM#$ROOT_DIR/}" "$SASS_VERSION"
done
