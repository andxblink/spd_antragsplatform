# SPD Antragsplatform public source

This repository is a source distribution for the SPD Antragsplatform layout
plugin on top of Antragsgrün. It contains the complete upstream Antragsgrün
source snapshot plus the modified `plugins/spd_layout/` tree.

## Source identity

- Upstream project: https://github.com/CatoTH/antragsgruen
- Upstream version: `v4.17.1`
- Upstream commit: `9e0e9f39622b7ee16c46d6e4b7756504262ef6ff`
- SPD plugin source revision: `1abd202f8d2dec1a2ccbc649de4db20da148950f`
- Plugin directory: `plugins/spd_layout/`

The plugin is selectable as the `std` layout and is not a replacement for the
upstream application. The upstream `LICENCE` and the plugin's AGPL licence
file are included in this distribution.

## Scope

This public source distribution intentionally excludes private deployment
credentials, production configuration, internal infrastructure state, test
worktrees, audit history, and operator-only runbooks. The corresponding
deployment repository remains separate.

Before presenting this URL as the source for a live deployment, verify that
the deployment manifest names this exact upstream revision and plugin
revision.
