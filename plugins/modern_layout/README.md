# Modern layout

A layout plugin for Antragsgrün in the visual language of the shadcn/ui admin
kit: a full-height navigation rail, a top bar with search, content as flat
cards, a neutral colour ramp and Lucide icons. It is a layout, not a fork:
upstream's views, controllers and permission logic are used unchanged, and
everything not overridden falls through to the base hooks.

## Use

Add `modern_layout` to `plugins` in `config/config.json`, then choose
"Modern" under the consultation's appearance settings. The plugin needs no
configuration of its own.

## How it is built

| Part | What it does |
|---|---|
| `LayoutHooks.php` | The rail (`beginPage`), the top bar (`logoRow`), the compact navigation below the rail breakpoint (`beforeContent`), the footer, and the status summary on motions and amendments |
| `assets/_modern.scss` | Tokens, then upstream's full partial list from `web/css/layout-classic.scss`, then this layout's rules. Compiled by `layout-modern_layout.scss` |
| `assets/shared/` | The Lucide icon font subset with its `@font-face`, and `modern-layout.js`, published once by `SharedAssets` for every layout built on this one |
| `MotionStatusPresenter.php`, `StatusPresentation.php` | A readable status summary around upstream's authoritative status text. Shown only where upstream shows the status (`motiondataMode`) |
| `messages/{en,de}/modern_layout.php` | The strings this layout adds. English is canonical; admins can override them under "Edit texts" |

The navigation is not rebuilt: the rail decorates the permission-filtered
`getStdNavbarHeader()` output and renders upstream's own consultation sidebar
view, so privileges and queries stay upstream's.

`modern-layout.js` handles the few places no hook reaches (grouping the
initiator form into two cards, feed rows with copy and open actions, edit
buttons in card headers, labels for the admin list's selection checkboxes,
underlines that skip icons). Each transform is idempotent and does nothing
when the markup it expects is absent.

## Colour rules

- Blue (`$accent`) is interaction: buttons, links, the selected rail entry,
  focus rings.
- `$identity` is the brand: the top band and the rail brand, never a control.
  Neutral zinc by default.
- Green is the one create action, "Antrag stellen"; red (`$destructive`) is
  deletion only.

Text and boundary colours are measured against WCAG 2.1 AA; the ratios are
recorded next to the tokens in `_modern.scss`.

## Building a branded variant

A variant is a separate plugin that extends these classes, so this plugin does
not need to be enabled for it:

- Stylesheet: assign `$identity` and `$identityHover`, then
  `@import "../../modern_layout/assets/modern";`.
- Hooks: extend `LayoutHooks` and override only `wordmark()`,
  `sourceOfferHtml()` and `messageCategory()` (return the variant's plugin id
  and ship the same message keys, since Antragsgrün only registers message
  categories of enabled plugins).
- Asset bundle: extend `VersionedAssetBundle` and depend on `SharedAssets`.

## Build

The compiled CSS is committed, because the Docker image has no Sass. Upstream's
`pnpm run build` compiles `plugins/**/*.scss` with `loadPaths: ['web/']`,
which is what `@import "css/variables"` relies on.

## Limits

Light mode only. Admin screens still load upstream's separately compiled
`backend.css`; this layout's admin rules override it. Bootstrap 3 markup and
Vue components are styled, not replaced.

## Licence

AGPL-3.0, as Antragsgrün (`LICENCE`). The Lucide icon subset is ISC
(`assets/shared/fonts/LUCIDE-LICENSE`).
