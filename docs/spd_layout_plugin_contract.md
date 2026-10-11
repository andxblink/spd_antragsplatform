# SPD layout plugin contract

This document defines the seam between Antragsgrün and the layout plugins. The
shared implementation lives in `plugins/modern_layout`; the SPD integration
lives in `plugins/spd_layout`. It is intentionally narrower than the
implementation. An upstream upgrade must be reviewed against this contract
before release.

## Supported interface

| Surface | Plugin implementation | Rule |
|---|---|---|
| Plugin registration | `plugins/modern_layout/Module.php`, `plugins/spd_layout/Module.php` | Modern exposes the neutral layout when enabled. SPD registers its own `std` layout and does not require the Modern module to be enabled. |
| Consultation settings | `plugins/spd_layout/Module.php`, `plugins/spd_layout/ConsultationSettings.php`, `plugins/spd_layout/LayoutSettings.php` | Use upstream's specialized-layout seam for server-side title formatting. |
| Translations | `plugins/modern_layout/messages/en/modern_layout.php`, `plugins/modern_layout/messages/de/modern_layout.php`, `plugins/spd_layout/messages/en/spd_layout.php`, `plugins/spd_layout/messages/de/spd_layout.php` | Override only approved wording. Upstream remains the fallback; the SPD message category carries the shared layout keys because only enabled plugins register categories. |
| Assets | `plugins/modern_layout/Assets.php`, `plugins/modern_layout/SharedAssets.php`, `plugins/modern_layout/VersionedAssetBundle.php`, `plugins/modern_layout/ThumbnailAssets.php`, `plugins/spd_layout/Assets.php`, `plugins/spd_layout/ThumbnailAssets.php` | Publish from each plugin source path. The SPD bundle reuses Modern's shared font CSS and JavaScript. Do not copy assets into core. |
| Layout hooks | `plugins/modern_layout/LayoutHooks.php` extends upstream hooks; `plugins/spd_layout/LayoutHooks.php` extends Modern | Use upstream hook methods and the narrow SPD overrides. Preserve permission and model ownership. |
| Status display | `plugins/modern_layout/MotionStatusPresenter.php`, `plugins/modern_layout/StatusPresentation.php` | Keep Antragsgrün's formatted status and the applicable date line. Do not add a second generic status-name summary or mutate legal-record data. |
| Recovery delivery | `plugins/spd_layout/RecoveryController.php`, `plugins/spd_layout/RecoveryDeliveryResult.php` | Keep the upstream recovery route, lock and transaction state, and classify only its explicit translated failure marker. |
| Cookie defaults | `plugins/spd_layout/Module.php::getSessionCookieSettings()` | Only deployment-aware cookie attributes. Authentication remains upstream. |

## Explicitly prohibited coupling

The plugin must not:

- edit files under the upstream checkout;
- write motion, amendment, vote, author, timestamp, comment, status, or
  publication data merely to render a page;
- replace upstream permission filtering or navigation queries;
- inherit a private upstream controller when the native route satisfies the
  product requirement;
- infer security outcomes by scraping translated HTML;
- treat unrelated recovery flashes as transport failures;
- depend on arbitrary route substrings when a route-aware helper exists;
- add another broad DOM observer or duplicate the application shell. The
  existing observer in `plugins/modern_layout/assets/shared/modern-layout.js` is a
  documented maintainability residual;
- treat a compiled asset or a public URL as verified without a source hash.

## Current migration inventory

| Current surface | Classification | Target |
|---|---|---|
| `plugins/modern_layout/Module.php` and `plugins/spd_layout/Module.php` registration | keep | Supported plugin interface; Modern is optional for the SPD variant |
| `plugins/spd_layout/Module.php` recovery controller map | isolate | Required until upstream recovery rolls back failed-delivery state |
| `plugins/spd_layout/ConsultationSettings.php` and `plugins/spd_layout/LayoutSettings.php` | keep | Server-side title/layout adapter |
| `plugins/spd_layout/Module.php` cookie settings | isolate | Small deployment adapter |
| `plugins/modern_layout/LayoutHooks.php` navigation and layout hooks | simplify | One semantic shell, supported hooks first; the rail and mobile fallback share one request-local `Layout::getStdNavbarHeader()` result |
| `plugins/modern_layout/assets/shared/modern-layout.js` DOM transforms | isolate then reduce | Server-rendered markup or scoped enhancement |
| `plugins/modern_layout/MotionStatusPresenter.php` and `plugins/modern_layout/StatusPresentation.php` | keep and constrain | Pure formatter with legal fixtures; retain upstream status wording and date line |
| `plugins/spd_layout/RecoveryController.php` | retain as a narrow adapter | Upstream recovery plus transaction and failed-delivery rollback |
| Partial translations in both plugins | keep | Versioned plugin overrides |
| SCSS, CSS, Lucide font, logo and thumbnails in both plugins | keep | Read-only plugin assets |

## Compatibility requirements

The contract is tested against both the production-pinned Antragsgrün reference
and a clean current-upstream-main reference. The following must remain stable:

- plugin loading and selected layout resolution;
- anonymous, member, admin, and super-admin permission filtering;
- consultation-relative URLs and legal links;
- translation merge behavior;
- asset publication and generated hashes;
- authentication and recovery behavior;
- exact legal text, line numbers, statuses, votes, authors, timestamps, and
  publication history;
- desktop rail, tablet layout, and phone drawer semantics.

If an upstream hook is absent in one reference, the integration cannot be
merged silently. It requires an adapter decision and a new compatibility test.
