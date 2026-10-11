# Transport and request-security boundary

The application is configured for HTTPS URL generation through
`APP_PROTOCOL=https`, and the deployment supplies the private-network proxy
range through `TRUSTED_PROXIES`. The plugin contributes `Secure`, `HttpOnly`,
and `SameSite=Lax` cookie settings when that protocol is active.

The application database user is separate from the MariaDB root account.
`DB_USER` and `DB_PASSWORD` are used by web, worker, and cron; the root-only
`DB_ROOT_PASSWORD` is used only by MariaDB initialization and approved
maintenance procedures. A deployment with `DB_USER=root` is rejected.

Caddy enforces `nosniff`, same-origin framing, a no-referrer policy, and a
restrictive permissions policy. The CSP is report-only for now because the
upstream application still emits inline JavaScript. It must be migrated to
nonce/hash-backed scripts before switching to an enforcing CSP.

Password-recovery URLs contain a one-time bearer code in the upstream `code`
query parameter. Caddy replaces that value with `REDACTED` in access logs, and
the plugin removes the code, recoveryCode, and email parameters from browser
history after the recovery form has received them. The upstream controller
still owns token validation and expiry. A future upstream-compatible exchange
endpoint should remove the token from the request URL entirely.

HSTS is intentionally deferred. It requires confirmation that every intended
hostname, including any future `www` or forum hostname, is HTTPS-capable before
the preload-style policy is enabled.
