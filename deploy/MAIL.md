# Mail and account-recovery gate

Production must not silently run with the upstream `None` mail transport. The
Compose file therefore requires an explicit `MAILER_DISABLED` value in the
host-only `.env`; the deploy script accepts only `false` and a reviewed
`mail-verified.json` marker. The marker records provider verification without
containing credentials:

```json
{
  "verified": true,
  "sender_verified": true,
  "bounce_address": "bounces@spd-antragsplatform.de",
  "provider": "operator-recorded-provider",
  "checked_utc": "2026-07-30T00:00:00Z"
}
```

The transport is Postmark over SMTP. The Antragsgrün image reads mail
settings only from `MAILER_DSN` (or `SMTP_*`) and turns the DSN into an SMTP
transport, so Postmark's API transport is not used. In the host-only `.env`:

```sh
MAILER_DISABLED=false
MAILER_DSN=smtp://SERVER_TOKEN:SERVER_TOKEN@smtp.postmarkapp.com:587
```

`SERVER_TOKEN` is the Postmark server API token, used as both user name and
password. Compose passes `MAILER_DSN` to web, worker and cron; the deploy
script refuses `MAILER_DISABLED=false` without an authenticated DSN. The
token stays in the host `.env` and the secret store and is never committed.

Postmark prerequisites: a server for this site using its default transactional
stream, and `spd-antragsplatform.de` verified as sender domain (DKIM TXT
record and the Return-Path CNAME to `pm.mtasv.net`) so `noreply@` is accepted.
Postmark handles bounces through that Return-Path domain; record it as
`bounce_address` in the marker.

Before writing `mail-verified.json`, test registration, invitation,
notification, and password-recovery success and failure paths, including the
bounce address and rate limits.

Password recovery is safe against failed delivery: the `spd_layout` recovery
adapter wraps upstream's token write and the send in one transaction and rolls
the token and rate-limit write back when the transport reports a failure. An
account can still be stranded by a message the transport accepts and then
drops, which is why the bounce path is part of the test. A disabled transport
creates no token.
