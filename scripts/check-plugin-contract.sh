#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
MODULE="$ROOT_DIR/plugins/spd_layout/Module.php"
HOOKS="$ROOT_DIR/plugins/modern_layout/LayoutHooks.php"
SPD_HOOKS="$ROOT_DIR/plugins/spd_layout/LayoutHooks.php"
PRESENTER="$ROOT_DIR/plugins/modern_layout/MotionStatusPresenter.php"
STATUS_PRESENTATION="$ROOT_DIR/plugins/modern_layout/StatusPresentation.php"
RECOVERY_RESULT="$ROOT_DIR/plugins/spd_layout/RecoveryDeliveryResult.php"
CONTRACT="$ROOT_DIR/docs/spd_layout_plugin_contract.md"

for file in "$MODULE" "$HOOKS" "$SPD_HOOKS" "$PRESENTER" "$STATUS_PRESENTATION" "$RECOVERY_RESULT" "$CONTRACT"; do
  test -f "$file" || { echo "ERROR: missing plugin contract file: $file" >&2; exit 1; }
done

grep -Fq 'getProvidedLayouts' "$MODULE" || { echo 'ERROR: layout registration missing' >&2; exit 1; }
grep -Fq 'getProvidedTranslations' "$MODULE" || { echo 'ERROR: translation registration missing' >&2; exit 1; }
grep -Fq 'getConsultationSettingsClass' "$MODULE" || { echo 'ERROR: consultation settings seam missing' >&2; exit 1; }
grep -Fq 'getSessionCookieSettings' "$MODULE" || { echo 'ERROR: cookie adapter missing' >&2; exit 1; }
grep -Fq 'MotionStatusPresenter' "$HOOKS" || { echo 'ERROR: status adapter is not wired' >&2; exit 1; }
grep -Fq 'class LayoutHooks extends ModernLayoutHooks' "$SPD_HOOKS" || {
  echo 'ERROR: the SPD hooks must extend the Modern layout' >&2
  exit 1
}
# The Modern layout is brand-neutral: nothing SPD-specific may live in it.
if grep -rniE 'spd|antragsplatform|e3000f' "$ROOT_DIR/plugins/modern_layout" \
    --include='*.php' --include='*.js' --include='*.scss' --include='*.css' >/dev/null; then
  grep -rniE 'spd|antragsplatform|e3000f' "$ROOT_DIR/plugins/modern_layout" \
    --include='*.php' --include='*.js' --include='*.scss' --include='*.css' | head -5 >&2
  echo 'ERROR: SPD-specific content in plugins/modern_layout' >&2
  exit 1
fi
grep -Fq 'StatusPresentation::kind' "$PRESENTER" || { echo 'ERROR: pure status seam is not wired' >&2; exit 1; }
grep -Fq 'StatusPresentation::dateLine' "$PRESENTER" || { echo 'ERROR: pure date seam is not wired' >&2; exit 1; }
grep -Fq 'RecoveryDeliveryResult::reportsFailure' "$ROOT_DIR/plugins/spd_layout/RecoveryController.php" || {
  echo 'ERROR: recovery delivery seam is not wired' >&2
  exit 1
}
grep -Fq 'private function upstreamNavigationHtml' "$HOOKS" || { echo 'ERROR: upstream navigation seam missing' >&2; exit 1; }
test "$(rg -c 'Layout::getStdNavbarHeader\(' "$HOOKS")" = 1 || {
  echo 'ERROR: upstream navigation must be generated through one request-local seam' >&2
  exit 1
}

if rg -n --pcre2 '\->(status|dateResolution|dateSubmission|dateCreation)\s*=(?!=)' "$PRESENTER"; then
  echo 'ERROR: status presenter must not rewrite model records' >&2
  exit 1
fi

if rg -n --pcre2 '\->(status|dateResolution|dateSubmission|dateCreation)\s*=' "$STATUS_PRESENTATION"; then
  echo 'ERROR: pure status presentation seam must not write record fields' >&2
  exit 1
fi

echo 'plugin contract: ok'
