# Antragsplatform layouts for Antragsgrün

Two layout plugins for [Antragsgrün](https://github.com/CatoTH/antragsgruen),
the motion and amendment platform by Tobias Hößl:

- `plugins/modern_layout`: a brand-neutral, current layout. A full-height
  navigation rail, a top bar with search, content as flat cards, a neutral
  colour ramp and Lucide icons, with text contrast measured against WCAG 2.1 AA.
  See its [README](plugins/modern_layout/README.md).
- `plugins/spd_layout`: the variant running at
  [spd-antragsplatform.de](https://spd-antragsplatform.de). It sets the SPD
  colours, wordmark and wording on top of `modern_layout`, and adds a
  password-recovery adapter and cookie settings for that deployment.

These are plugins, not a fork. Antragsgrün's views, controllers, permissions
and data stay untouched; the plugins use its layout hooks, asset bundles and
message catalogues. Everything a hook does not override falls through to
Antragsgrün's own behaviour.

## Use

Copy `plugins/modern_layout` (and `plugins/spd_layout` for the SPD variant)
into the `plugins/` directory of an Antragsgrün installation, add the plugin id
to `plugins` in `config/config.json`, and choose the layout in the
consultation's appearance settings. `spd_layout` needs `modern_layout` on disk
but not enabled.

Tested with Antragsgrün v4.17.1 and v4.17.2 and checked against its current
`main`. The compiled CSS is committed because the official Docker image has no
Sass; Antragsgrün's own `pnpm run build` compiles `plugins/**/*.scss`.

## Checks

```sh
scripts/check-plugins.sh
```

runs the checks that need only this repository (PHP, Git, ripgrep, Node).
Compatibility checks take an Antragsgrün checkout as reference:
`scripts/check-generated-assets.sh` (CSS reproducible with Dart Sass 1.105.1),
`scripts/check-upstream-imports.sh` and `scripts/test-plugin-icons.py`.
`scripts/test-stack.sh` starts a disposable local stack with a seeded
consultation for browser checks (`scripts/run-browser-matrix.mjs`).

## Deployment reference

`deploy/docker-compose.yml` and `deploy/antragsplatform.caddy` are the
configuration of the running site: the official Antragsgrün image pinned by
digest, both plugins mounted read-only, MariaDB and Redis. Secrets come from a
host-only `.env`. `deploy/MAIL.md` and `deploy/SECURITY.md` describe the mail
transport and the security settings.

## Release 2026-10-11

This commit is the corresponding source of the release running at
spd-antragsplatform.de, as the site's footer links it.

- Antragsgrün v4.17.1, commit `9e0e9f39622b7ee16c46d6e4b7756504262ef6ff`
- Image `tobiashoessl/antragsgruen@sha256:2e9b68f791364fed12a54548c49985a6ba9bda1b7005cd8c087c685ea4a5ee49`
- `plugins/` tree `6f120e2d164dbc4d64224fb3eeaf969f656f0c5b`

`git rev-parse HEAD:plugins` prints the tree id above, and
`release-manifest.json` records the same id together with the stylesheet hash
and file fingerprint the deployment verifies.

This repository is published from a private working repository, one commit per
release. It was developed with substantial AI assistance.

## Licence

AGPL-3.0, as Antragsgrün (`LICENCE`). The Lucide icon subset is ISC
(`plugins/modern_layout/assets/shared/fonts/LUCIDE-LICENSE`).
