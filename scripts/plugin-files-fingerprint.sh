#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="${1:?usage: plugin-files-fingerprint.sh PLUGIN_DIRECTORY}"
ROOT_DIR="$(cd "$ROOT_DIR" && pwd)"

hash_file() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1" | awk '{print $1}'
    return
  fi
  shasum -a 256 "$1" | awk '{print $1}'
}

hash_stream() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum | awk '{print $1}'
    return
  fi
  shasum -a 256 | awk '{print $1}'
}

manifest_file="$(mktemp)"
trap 'rm -f "$manifest_file"' EXIT

# The manifest is deliberately path-sensitive. Two plugin trees with the same
# bytes under different names are different releases. Repository filenames do
# not contain newlines; rejecting one here keeps the line-oriented format
# unambiguous on the deployment host too.
find "$ROOT_DIR" -type f -print | LC_ALL=C sort | while IFS= read -r file; do
  relative="${file#"$ROOT_DIR"/}"
  case "$relative" in
    *$'\n'*)
      echo "ERROR: plugin filename contains a newline: $relative" >&2
      exit 1
      ;;
  esac
  printf '%s  %s\n' "$(hash_file "$file")" "$relative"
done > "$manifest_file"

hash_stream < "$manifest_file"
