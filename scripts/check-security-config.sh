#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CADDY="$ROOT/deploy/antragsplatform.caddy"
COMPOSE="$ROOT/deploy/docker-compose.yml"
MODULE="$ROOT/plugins/spd_layout/Module.php"
LAYOUT_JS="$ROOT/plugins/modern_layout/assets/shared/modern-layout.js"

for marker in \
  'X-Content-Type-Options "nosniff"' \
  'X-Frame-Options "SAMEORIGIN"' \
  'Referrer-Policy "no-referrer"' \
  'Permissions-Policy "camera=(), geolocation=(), microphone=()"' \
  'Content-Security-Policy-Report-Only' \
  'replace code REDACTED' \
  'replace recoveryCode REDACTED' \
  'replace email REDACTED'; do
    grep -Fq "$marker" "$CADDY" || { echo "ERROR: missing Caddy security marker: $marker" >&2; exit 1; }
done

grep -Fq 'APP_PROTOCOL: https' "$COMPOSE" || { echo 'ERROR: production protocol is not HTTPS' >&2; exit 1; }
grep -Fq 'TRUSTED_PROXIES:' "$COMPOSE" || { echo 'ERROR: trusted proxy boundary is not configured' >&2; exit 1; }
grep -Fq "'secure' => strtolower((string)getenv('APP_PROTOCOL')) === 'https'" "$MODULE" || {
    echo 'ERROR: HTTPS cookie policy is missing' >&2
    exit 1
}
grep -Fq 'window.history.replaceState' "$LAYOUT_JS" || {
    echo 'ERROR: recovery URL cleanup is missing' >&2
    exit 1
}

echo "security configuration: ok (HTTPS cookies, headers, recovery URL and log redaction)"
