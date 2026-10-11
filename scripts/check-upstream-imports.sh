#!/usr/bin/env bash
# Compare the partial list in plugins/modern_layout/assets/_modern.scss with
# upstream's layout-classic.scss in a given checkout, in order.
#
#   scripts/check-upstream-imports.sh UPSTREAM_CHECKOUT
#
# A layout replaces layout-classic.css wholesale, so a partial upstream adds
# and we do not import leaves its screens unstyled without any error (4.18's
# _debate.scss was found that way). Partials listed in
# scripts/fixtures/pending-upstream-partials.txt are known and waiting for the
# release that ships them; anything else missing or extra fails.

set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
UPSTREAM=${1:?usage: check-upstream-imports.sh UPSTREAM_CHECKOUT}
CLASSIC="$UPSTREAM/web/css/layout-classic.scss"
MODERN="$ROOT_DIR/plugins/modern_layout/assets/_modern.scss"
PENDING="$ROOT_DIR/scripts/fixtures/pending-upstream-partials.txt"

[[ -f "$CLASSIC" ]] || { echo "ERROR: missing $CLASSIC" >&2; exit 1; }

# Upstream's partial imports, without the variables and font-face imports a
# layout handles itself.
upstream=$(grep -oE '^@import "[a-z_]+";' "$CLASSIC" | sed -E 's/@import "([a-z_]+)";/\1/' | grep -vx 'variables')
ours=$(grep -oE '^@import "css/[a-z_]+";' "$MODERN" | sed -E 's/@import "css\/([a-z_]+)";/\1/' | grep -vx 'variables')
pending=$(grep -vE '^\s*(#|$)' "$PENDING" | awk '{print $1}' || true)

expected=$(printf '%s\n' "$upstream" | grep -vxF -f <(printf '%s\n' "$pending" | sed '/^$/d') || true)
if [[ "$expected" != "$ours" ]]; then
  echo "ERROR: partial imports differ from $(basename "$UPSTREAM") layout-classic.scss" >&2
  diff <(printf '%s\n' "$expected") <(printf '%s\n' "$ours") | sed 's/^/  /' >&2 || true
  echo "  (< upstream only, > ours only; pending: $(printf '%s ' $pending))" >&2
  exit 1
fi
echo "upstream imports: ok ($(printf '%s\n' "$upstream" | wc -l | tr -d ' ') partials, $(basename "$UPSTREAM"))"
