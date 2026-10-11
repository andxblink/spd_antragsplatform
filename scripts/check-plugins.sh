#!/usr/bin/env bash
# Checks for the two layout plugins that need nothing beyond this repository:
# php, git, rg and node. Upstream compatibility checks (PHPStan, CSS
# compilation, icon contract) need an Antragsgrün checkout; see the README.

set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
failures=()

check() {
  local label=$1
  shift
  if "$@"; then
    printf 'ok: %s\n' "$label"
  else
    printf 'FAILED: %s\n' "$label" >&2
    failures+=("$label")
  fi
}

php_lint() {
  local files file status=0
  files=$(git -C "$ROOT_DIR" ls-files 'plugins/*.php' 'scripts/*.php') || return 1
  [[ -n "$files" ]] || return 1
  while IFS= read -r file; do
    php -l "$ROOT_DIR/$file" >/dev/null || status=1
  done <<< "$files"
  return "$status"
}

check 'PHP syntax' php_lint
check 'plugin contract' "$ROOT_DIR/scripts/check-plugin-contract.sh"
check 'maintainability contract' bash "$ROOT_DIR/scripts/check-maintainability-contract.sh"
check 'responsive contract' bash "$ROOT_DIR/scripts/check-responsive-contract.sh"
check 'security contract' "$ROOT_DIR/scripts/check-security-config.sh"
check 'status presentation fixtures' php "$ROOT_DIR/scripts/test-status-presentation.php"
check 'recovery delivery fixtures' php "$ROOT_DIR/scripts/test-recovery-delivery.php"
check 'layout script syntax' node --check "$ROOT_DIR/plugins/modern_layout/assets/shared/modern-layout.js"

if ((${#failures[@]} > 0)); then
  printf '\n%d check(s) failed:\n' "${#failures[@]}" >&2
  printf ' - %s\n' "${failures[@]}" >&2
  exit 1
fi
echo 'plugin checks passed'
