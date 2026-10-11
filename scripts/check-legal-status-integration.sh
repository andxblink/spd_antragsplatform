#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
PRESENTER="$ROOT_DIR/plugins/modern_layout/MotionStatusPresenter.php"
RECOVERY="$ROOT_DIR/plugins/spd_layout/RecoveryController.php"
RECOVERY_RESULT="$ROOT_DIR/plugins/spd_layout/RecoveryDeliveryResult.php"
HOOKS="$ROOT_DIR/plugins/modern_layout/LayoutHooks.php"
MODULE="$ROOT_DIR/plugins/spd_layout/Module.php"

for file in "$PRESENTER" "$RECOVERY" "$RECOVERY_RESULT" "$HOOKS" "$MODULE"; do
  test -f "$file"
  php -l "$file" >/dev/null
done

grep -q 'getFormattedStatus' "$PRESENTER"
grep -q "str_replace('</string>', '</small>'" "$PRESENTER"
grep -q 'public function beforeMotionView' "$HOOKS"
grep -q 'public function getConsultationMotionLineContent' "$HOOKS"
grep -q 'public function getConsultationAmendmentLineContent' "$HOOKS"
grep -q 'public function getAmendmentViewData' "$HOOKS"

if rg --pcre2 -n '\->status\s*=(?!=)' "$PRESENTER"; then
  echo 'status presenter must not rewrite model status' >&2
  exit 1
fi

grep -q "\['transport'\] ?? null) === 'none'" "$RECOVERY"
grep -q 'FOR UPDATE' "$RECOVERY"
grep -q 'transaction->rollBack' "$RECOVERY"
grep -q 'sessionHasMailFailure' "$RECOVERY"
grep -q 'RecoveryDeliveryResult::reportsFailure' "$RECOVERY"
grep -q "getFlash('error', null, true)" "$RECOVERY"
grep -q 'restorePreviousErrorFlash' "$RECOVERY"
if grep -Fq 'getHtml' "$RECOVERY" || grep -Fq 'strip_tags' "$RECOVERY"; then
  echo 'recovery adapter must not parse translated HTML' >&2
  exit 1
fi
grep -q "\$route !== 'user/recovery'" "$MODULE"
grep -q 'RecoveryController::class' "$MODULE"

php "$ROOT_DIR/scripts/test-recovery-delivery.php"

# Same references as the release gate: the pinned release always, the
# forward-compatibility checkout when one is named.
for upstream in "${PINNED_UPSTREAM:-$ROOT_DIR/temp/audit-upstream-v4.17.1}" ${CURRENT_UPSTREAM:+"$CURRENT_UPSTREAM"}; do
  test -f "$upstream/controllers/UserController.php"
  grep -q 'public function actionRecovery' "$upstream/controllers/UserController.php"
done

echo 'legal status and recovery integration contract: ok'
