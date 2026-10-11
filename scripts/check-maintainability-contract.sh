#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
MODERN="$ROOT_DIR/plugins/modern_layout"
SPD="$ROOT_DIR/plugins/spd_layout"
HOOKS="$MODERN/LayoutHooks.php"
LAYOUT_JS="$MODERN/assets/shared/modern-layout.js"

# Every string the layouts add goes through a message category, with English
# as the canonical base and German beside it. Modern's keys live in
# modern_layout; the SPD variant answers them from its own spd_layout
# catalogue (modern_layout need not be enabled there), plus its own keys.
# A key used in code but missing from a catalogue renders as its raw id.
# Literal keys passed to $this->t() or Yii::t(), and the date keys that
# StatusPresentation returns as data ('key' => ...) for the presenter.
keys_in() {
  grep -rhoP "(\\\$this->t\('|Yii::t\('$2', '|'key' => ')\K[a-z_]+" "$1" --include='*.php' | sort -u
}
modern_keys=$(keys_in "$MODERN" modern_layout)
spd_keys=$( { printf '%s\n' "$modern_keys"; keys_in "$SPD" spd_layout; } | sort -u)
[[ -n "$modern_keys" ]] || { echo 'ERROR: no modern_layout message keys found in code' >&2; exit 1; }
check_catalogue() {
  local catalogue=$1 keys=$2 key
  test -f "$catalogue" || { echo "ERROR: missing message catalogue: $catalogue" >&2; exit 1; }
  for key in $keys; do
    grep -Eq "^\s*'$key'\s*=>" "$catalogue" || {
      echo "ERROR: message key $key missing in $catalogue" >&2
      exit 1
    }
  done
}
for lang in en de; do
  check_catalogue "$MODERN/messages/$lang/modern_layout.php" "$modern_keys"
  check_catalogue "$SPD/messages/$lang/spd_layout.php" "$spd_keys"
done

# The DOM pass lives in a versioned asset; its labels come from the message
# catalogue as data attributes on its script tag, never as literals.
for label in feed-open feed-copy select-row select-row-untitled; do
  grep -Fq "'data-$label'" "$HOOKS" || {
    echo "ERROR: layout script label is not injected from translations: $label" >&2
    exit 1
  }
done
grep -Fq 'document.currentScript.dataset' "$LAYOUT_JS" || {
  echo 'ERROR: layout script does not read its labels from its tag' >&2
  exit 1
}
[[ "$(grep -c '<script>' "$HOOKS")" == 1 ]] || {
  echo 'ERROR: only the head stylesheet fix may stay an inline script' >&2
  exit 1
}

if grep -Fq '.observe(document.body' "$LAYOUT_JS"; then
  echo 'ERROR: layout observer must not watch document.body' >&2
  exit 1
fi
if grep -Fq '.innerHTML' "$LAYOUT_JS"; then
  echo 'ERROR: layout DOM rewrites must not assign innerHTML' >&2
  exit 1
fi
if grep -Eq "'[^']*[äöüÄÖÜß][^']*'" "$LAYOUT_JS"; then
  echo 'ERROR: German wording is hardcoded in the layout script' >&2
  exit 1
fi

grep -Fq "document.querySelectorAll('.userAdmin, .votingAdmin, .currentDebateAdmin')" "$LAYOUT_JS" || {
  echo 'ERROR: dynamic observer roots are not explicit' >&2
  exit 1
}
grep -Fq 'private function pathHasSegment' "$HOOKS" || {
  echo 'ERROR: route matching helper is missing' >&2
  exit 1
}
if grep -Fq 'str_contains($path' "$HOOKS" ||
   grep -Fq 'str_ends_with($path' "$HOOKS"; then
  echo 'ERROR: route classification still relies on substring heuristics' >&2
  exit 1
fi

for raw_color in \
  'rgba(22, 163, 74' \
  'rgba(239, 68, 68' \
  'rgba(245, 158, 11' \
  'rgba(59, 130, 246' \
  'rgba(255, 255, 255'; do
    if grep -Fq "$raw_color" "$MODERN/assets/_modern.scss"; then
      echo "ERROR: semantic color remains un-tokenized: $raw_color" >&2
      exit 1
    fi
done

echo 'layout maintainability contract: ok (scoped observer, translated labels, safe DOM construction)'
