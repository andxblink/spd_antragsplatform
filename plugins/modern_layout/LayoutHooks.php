<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

use app\components\UrlHelper;
use app\models\db\{Amendment, IComment, Motion};
use app\models\db\repostory\MotionRepository;
use app\models\layoutHooks\{Hooks, Layout};
use yii\helpers\Html;

/**
 * Structural overrides for the Modern layout.
 *
 * Only the hooks that need different markup are implemented. Everything not
 * listed here falls through to the base Hooks implementation, which keeps this
 * class small and keeps upstream template changes flowing through.
 *
 * Brand-neutral. A branded variant extends this class and overrides only the
 * protected seams below: wordmark(), sourceOfferHtml() and messageCategory().
 * The footer keeps upstream's attribution link.
 *
 * ---------------------------------------------------------------------------
 * The shell
 * ---------------------------------------------------------------------------
 * Reference is the shadcn/ui admin kit: a full-height left navigation rail, a
 * top bar carrying search, then the page content. Upstream is a centred header
 * with a horizontal nav and a right sidebar.
 *
 * `views/layouts/main.php` emits the children of `#page` as a flat list:
 *
 *     beginPage()  logoRow()  beforeContent()  $content
 *
 * so the rail is emitted from `beginPage()`, which is the first child of
 * `#page`, and the SCSS turns `#page` into a two column grid with the rail
 * spanning every row. This works on every page regardless of whether the
 * controller used `column1.php` (admin screens, no sidebar) or `column2.php`
 * (consultation and motion screens, right sidebar), because the rail sits
 * outside `.antragsgruen-content` entirely.
 *
 * Ordering note: Yii renders the inner view first and hands `main.php` a
 * finished `$content` string, so `$this->layout` is fully populated by the
 * time `beginPage()` runs. `renderSidebar()` already relies on that same
 * guarantee, so it is not a new assumption.
 */
class LayoutHooks extends Hooks
{
    /**
     * The permission-filtered upstream navbar is used by both the persistent
     * rail and the compact mobile navigation. Keep one request-local copy so
     * both shells are derived from the same upstream result instead of running
     * the menu builder twice.
     */
    private ?string $upstreamNavigationHtml = null;

    private function upstreamNavigationHtml(): string
    {
        return $this->upstreamNavigationHtml ??= Layout::getStdNavbarHeader();
    }

    /**
     * The text mark at the top of the rail. Neutral default: the site's own
     * title, as entered by its admin.
     */
    protected function wordmark(): string
    {
        return $this->consultation?->site->title ?? 'Antragsgrün';
    }

    /**
     * Extra link after the upstream attribution in the footer, for a
     * deployment's own source offer. None by default.
     */
    protected function sourceOfferHtml(): string
    {
        return '';
    }

    /**
     * The message category for strings this layout adds. A variant that is
     * active without this plugin enabled returns its own plugin id and ships
     * the same keys, since MessageSource only registers categories of enabled
     * plugins.
     */
    protected function messageCategory(): string
    {
        return 'modern_layout';
    }

    protected function t(string $key): string
    {
        return \Yii::t($this->messageCategory(), $key);
    }

    private ?MotionStatusPresenter $statusPresenter = null;

    private function statusPresenter(): MotionStatusPresenter
    {
        return $this->statusPresenter ??= new MotionStatusPresenter($this->messageCategory());
    }

    /**
     * Screens that are a dense data table rather than a document, and want the
     * full window width instead of a navigation rail.
     *
     * Only the Antragsliste for now. Route comes from `config/urls.php:58`,
     * which maps `<consultation>/admin/list` to `admin/motion-list/index`.
     */
    private function isTableView(): bool
    {
        $controller = \Yii::$app?->controller;

        return $controller !== null && $controller->getUniqueId() === 'admin/motion-list';
    }

    /**
     * The left navigation rail.
     *
     * Content is not re-derived. `getStdNavbarHeader()` already resolves
     * privileges, content pages, the motion list and login state, and
     * `layout->menusHtml` already holds the per-page menus that upstream would
     * have put in the right sidebar. Both are re-placed here rather than
     * rebuilt, so privilege handling stays upstream's problem.
     */
    public function beginPage(string $before): string
    {
        // The marker is what the stylesheet keys off, via
        // `body:has(.modernTableView)`. It changes the dense table presentation,
        // not the shell: tablet and desktop keep the persistent rail like
        // every other route. A class cannot be put on <body> from here because
        // main.php has already emitted the opening <body> tag.
        $out = '';
        if ($this->isTableView()) {
            $out .= '<span class="modernTableView" hidden></span>';
        }

        $home = Html::encode(UrlHelper::homeUrl() ?? '/');

        $out .= '<nav class="modernRail" id="modernRail" role="navigation" aria-label="' .
               Html::encode(\Yii::t('base', 'menu_main')) . '">';

        $out .= '<div class="modernRailBrand">';
        $out .= '<a href="' . $home . '" rel="home" class="modernRailWordmark">' .
                Html::encode($this->wordmark()) . '</a>';
        if ($this->consultation) {
            $out .= '<span class="modernRailConsultation">' . Html::encode($this->consultation->title) . '</span>';
        }
        $out .= '</div>';

        // Fill in the consultation menus on pages whose view never rendered
        // them, so the rail carries the same entries everywhere. Has to run
        // before the split below, because it appends to the same array.
        $this->ensureConsultationMenus();

        // The per-page menus upstream renders into the right sidebar: the
        // create button, Neuigkeiten, Neue Anträge, PDFs, and on a motion page
        // that motion's own actions. renderSidebar() below returns empty so
        // these are not rendered twice.
        //
        // "Antrag stellen" is the primary action and belongs at the top of the
        // rail, but `setSidebarCreateMotionButton()` appends it into the middle
        // of menusHtml. Split it out by its wrapper class rather than reordering
        // with CSS, so the DOM order matches the reading order for anyone on a
        // screen reader or with styles off.
        $create = '';
        $menus  = [];
        foreach ($this->layout->menusHtml as $menuHtml) {
            if (str_contains($menuHtml, 'createMotionHolder1')) {
                $create .= $menuHtml;
            } else {
                $menus[] = $menuHtml;
            }
        }

        // Decide the selected entry once, across the nav and the menus together.
        // These are two separate DOM passes, and letting each pick its own
        // winner puts two selected states on screen; resolving over the union
        // also means a page whose only entry is in a menu still marks it, which
        // is why /std/activitylog used to show nothing selected at all.
        $navHtml    = $this->upstreamNavigationHtml();
        $menusHtml  = implode('', $menus);
        $activeHref = $this->resolveActiveHref(
            array_merge($this->hrefsIn($navHtml), $this->hrefsIn($menusHtml))
        );

        $nav       = $this->decorateNav($navHtml, withIcons: true, split: true, activeHref: $activeHref);
        $menusHtml = $this->markActiveMenus($menusHtml, $activeHref);

        $out .= '<div class="modernRailScroll">';
        if ($create !== '') {
            $out .= '<div class="modernRailPrimary">' . $create . '</div>';
        }
        $out .= '<div class="modernRailNav">' . $nav['main'] . '</div>';
        // What upstream's renderSidebar() wraps around the menus. In upstream
        // that is the "Antragsgrün" promo box after them; a variant that
        // suppresses it through getAntragsgruenAd() leaves both empty.
        $menusHtml = $this->layout->preSidebarHtml . $menusHtml . $this->layout->postSidebarHtml;
        if ($menusHtml !== '') {
            $out .= '<div class="modernRailMenus">' . $menusHtml . '</div>';
        }
        $out .= '</div>';

        // Pinned to the bottom: administration and account first, then logout
        // again separated from those, so it is not hit by accident.
        {
            $out .= '<div class="modernRailFooter modernRailNav">';
            if ($nav['secondary'] !== '') {
                $out .= '<div class="modernRailSecondary">' . $nav['secondary'] . '</div>';
            }
            if ($nav['logout'] !== '') {
                $out .= '<div class="modernRailLogout">' . $nav['logout'] . '</div>';
            }
            $out .= '<div class="modernRailLegal">' . $this->legalLinksHtml() . '</div>';
            $out .= '</div>';
        }

        $out .= '</nav>';

        return $out;
    }

    /**
     * Render the consultation sidebar on pages whose own view never did.
     *
     * `menusHtml` is filled by *views*, and only seven views fill it: the
     * consultation, motion, amendment, voting and speech sidebars, plus the two
     * admin update screens. Every other page hands the rail an empty array, so
     * the rail changed shape as you moved through the site and entries vanished
     * on exactly the pages that have no other navigation. Measured before this:
     * `/` and `/std/feeds` supplied 5 boxes, `/std/admin` and `/std/myaccount`
     * supplied none.
     *
     * Upstream already builds every one of those boxes, with its own queries and
     * its own permission logic, so this runs that view rather than rebuilding
     * any part of it. `getConsultationSidebar()` returns a path *alias*
     * (`@app/views/consultation/sidebar`), which is what makes it renderable
     * from an admin controller: the alias resolves absolutely instead of against
     * the current controller's view directory.
     *
     * The parameters and their limits are copied from
     * `ConsultationController::consultationSidebar()`, which is the only caller
     * upstream has. Keep them in step with it.
     *
     * Three things make this safe to run on every page:
     *
     *  1. The view reads `$this->context->consultation` and `->layoutParams`,
     *     both declared on `controllers\Base`, which every controller here
     *     extends. `layoutParams` is the same object as `$this->layout`, so its
     *     appends land in the array read moments later in `beginPage()`.
     *  2. It only ever appends, and the guard means it runs at most once, so
     *     there is no path that produces two copies of a box.
     *  3. It is wrapped, because it is the one part of the rail that runs real
     *     queries. A layout that throws takes the whole page down with it, and
     *     no navigation aid is worth that. A failure costs the menus and nothing
     *     else.
     *
     * The guard keys on the news box because that is the one box the view emits
     * unconditionally. Keying on "is `menusHtml` empty" would be wrong: a motion
     * page has its own menus and still wants these.
     */
    private function ensureConsultationMenus(): void
    {
        if (!$this->consultation) {
            return;
        }

        foreach ($this->layout->menusHtml as $menuHtml) {
            if (str_contains($menuHtml, 'sidebar-box-news')) {
                return;
            }
        }

        $view = $this->consultation->getSettings()->getConsultationSidebar();
        if ($view === null) {
            return;
        }

        $controller = \Yii::$app?->controller;
        if (!$controller instanceof \app\controllers\Base) {
            return;
        }

        try {
            $controller->renderPartial($view, [
                'newestMotions'    => MotionRepository::getNewestByConsultation($this->consultation, 3),
                'newestAmendments' => Amendment::getNewestByConsultation($this->consultation, 5),
                'newestComments'   => IComment::getNewestByConsultation($this->consultation, 3),
            ]);
        } catch (\Throwable $e) {
            \Yii::warning('modern_layout: consultation menus not rendered: ' . $e->getMessage(), 'modern_layout');
        }
    }

    /**
     * Turn upstream's navbar `<ul>` into the rail's navigation.
     *
     * Three jobs, all of which need to look at individual entries, so this uses
     * DOM rather than regex over the markup:
     *
     *  1. Mark the active entry. Upstream hardcodes `class="active"` on the Home
     *     entry regardless of the current page
     *     (`StdHooks::getStdNavbarHeader()`), so it is stripped and recomputed by
     *     longest matching path prefix. Doing this in DOM also fixes a real bug
     *     in the regex version: on an entry that already had a class it emitted a
     *     second `class` attribute, and browsers keep the first, so the marker
     *     was silently dropped.
     *  2. Give every entry an icon. A rail of bare text labels has nothing to
     *     scan by.
     *  3. Split out Logout so it can be pinned to the bottom of the rail, away
     *     from the entries people actually click.
     *
     * @return array{main: string, secondary: string, logout: string}
     */
    private function decorateNav(string $navHtml, bool $withIcons, bool $split, ?string $activeHref): array
    {
        $empty = ['main' => $navHtml, 'secondary' => '', 'logout' => ''];

        $doc = $this->loadFragment($navHtml);
        if ($doc === null) {
            return $empty;
        }

        $xpath = new \DOMXPath($doc);
        $roots = $this->query($xpath, '//div[@id="modernNavRoot"]');
        if ($roots === null) {
            return $empty;
        }
        $root = $roots->item(0);
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        /** @var array{secondary: list<array{0: int, 1: string}>, logout: list<array{0: int, 1: string}>} $extracted */
        $extracted  = ['secondary' => [], 'logout' => []];

        $listItems = $this->query($xpath, './/li', $root);
        if ($listItems === null) {
            return $empty;
        }

        foreach ($listItems as $li) {
            if (!$li instanceof \DOMElement) {
                continue;
            }

            $classes = preg_split('/\s+/', trim($li->getAttribute('class'))) ?: [];
            $classes = array_values(array_filter($classes, static fn(string $c): bool => $c !== '' && $c !== 'active'));

            $anchors = $this->query($xpath, './/a', $li);
            $anchor  = $anchors?->item(0);
            if (!$anchor instanceof \DOMElement) {
                $li->setAttribute('class', implode(' ', $classes));
                continue;
            }

            $href = $anchor->getAttribute('href');

            if ($activeHref !== null && $href === $activeHref) {
                $classes[] = 'modernRailActive';
            }
            $li->setAttribute('class', implode(' ', $classes));

            $existingIcons = $this->query($xpath, './/span[contains(@class, "glyphicon")]', $anchor);
            if ($withIcons && ($existingIcons === null || $existingIcons->length === 0)) {
                $icon = $this->navIconFor($anchor);
                $span = $doc->createElement('span');
                $span->setAttribute('class', 'glyphicon glyphicon-' . $icon);
                $span->setAttribute('aria-hidden', 'true');
                $anchor->insertBefore($span, $anchor->firstChild);
            }

            $group = $this->navGroupFor($href);
            if ($split && $group !== 'main') {
                $extracted[$group][] = [$this->navRankFor($href), (string)$doc->saveHTML($li)];
                $li->parentNode?->removeChild($li);
            }
        }

        return [
            'main'      => $this->innerHtml($root),
            'secondary' => $this->navList($extracted['secondary']),
            'logout'    => $this->navList($extracted['logout']),
        ];
    }

    /**
     * Remove anchor IDs from the duplicated compact navigation.
     *
     * The rail and the phone navigation intentionally share upstream's
     * permission-filtered markup. Only the rail is canonical, though: keeping
     * IDs such as `homeLink` and `loginLink` in both copies violates the DOM
     * uniqueness contract even while the compact copy is hidden at rail
     * widths. The compact navigation does not use those IDs for behavior.
     */
    private function stripNavAnchorIds(string $navHtml): string
    {
        $doc = $this->loadFragment($navHtml);
        if ($doc === null) {
            return $navHtml;
        }

        $xpath = new \DOMXPath($doc);
        $anchors = $this->query($xpath, '//a[@id]');
        if ($anchors === null) {
            return $navHtml;
        }

        foreach ($anchors as $anchor) {
            if ($anchor instanceof \DOMElement) {
                $anchor->removeAttribute('id');
            }
        }

        $root = $this->query($xpath, '//div[@id="modernNavRoot"]')?->item(0);

        return $root instanceof \DOMElement ? $this->innerHtml($root) : $navHtml;
    }

    /**
     * Parse a markup fragment, or null if it cannot be parsed.
     *
     * The wrapper element is what makes the fragment addressable and, with
     * NOIMPLIED, keeps libxml from inventing an <html><body> around it. The
     * XML declaration is the standard workaround for DOMDocument defaulting to
     * ISO-8859-1, which would otherwise mangle every umlaut in the rail.
     */
    private function loadFragment(string $html): ?\DOMDocument
    {
        if ($html === '' || !class_exists(\DOMDocument::class)) {
            return null;
        }

        $doc          = new \DOMDocument();
        $previousMode = libxml_use_internal_errors(true);
        $loaded       = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="modernNavRoot">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousMode);

        return $loaded ? $doc : null;
    }

    /**
     * Query a document while normalising DOMXPath's false-on-error return.
     *
     * @return \DOMNodeList<\DOMNode|\DOMNameSpaceNode>|null
     */
    private function query(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): ?\DOMNodeList
    {
        $nodes = $xpath->query($expression, $context);

        return $nodes instanceof \DOMNodeList ? $nodes : null;
    }

    /**
     * Serialise a node's children, dropping the wrapper `loadFragment()` added.
     */
    private function innerHtml(\DOMElement $root): string
    {
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= (string)$root->ownerDocument?->saveHTML($child);
        }

        return $out;
    }

    /**
     * Join an extracted group into a `<ul>`, in rank order.
     *
     * Source order cannot be used: "Neue Inhalts-Seite im Menü anlegen" is the
     * first entry upstream emits, so taking the entries as they come would put
     * it above Einstellungen rather than below it.
     *
     * `usort` is stable as of PHP 8.0, so entries that share a rank keep the
     * order upstream gave them.
     *
     * @param list<array{0: int, 1: string}> $entries
     */
    private function navList(array $entries): string
    {
        if ($entries === []) {
            return '';
        }

        usort($entries, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        return '<ul class="nav">' . implode('', array_column($entries, 1)) . '</ul>';
    }

    /**
     * Match URL path segments, not arbitrary substrings in slugs.
     *
     * Navigation markup from upstream exposes only hrefs, so route-derived
     * grouping is the stable seam available to a layout plugin. Segment
     * matching keeps a consultation slug such as `administer` from being
     * mistaken for `/admin`, and makes the exception boundaries explicit.
     */
    private function pathHasSegment(string $path, string $segment): bool
    {
        $segments = array_values(array_filter(
            explode('/', trim((string)parse_url($path, PHP_URL_PATH), '/')),
            static fn(string $value): bool => $value !== ''
        ));

        return in_array($segment, $segments, true);
    }

    private function pathEndsWithSegment(string $path, string $segment): bool
    {
        $segments = array_values(array_filter(
            explode('/', trim((string)parse_url($path, PHP_URL_PATH), '/')),
            static fn(string $value): bool => $value !== ''
        ));

        return $segments !== [] && end($segments) === $segment;
    }

    /**
     * Which block of the rail an entry belongs in.
     *
     * Administration and Account are destinations people visit occasionally, not
     * the ones they navigate the consultation with, so they sit at the foot of
     * the rail. Logout is separated again from those, so it is not a neighbour
     * of anything routine.
     */
    private function navGroupFor(string $href): string
    {
        $path = rtrim((string)parse_url($href, PHP_URL_PATH), '/');

        if ($this->pathHasSegment($path, 'logout')) {
            return 'logout';
        }
        if ($this->pathHasSegment($path, 'myaccount') ||
            $this->pathEndsWithSegment($path, 'admin') ||
            $this->pathEndsWithSegment($path, 'page')) {
            return 'secondary';
        }

        return 'main';
    }

    /**
     * Position within the foot of the rail.
     *
     * "Neue Inhalts-Seite im Menü anlegen" is an administration action, not a
     * way to move around the consultation, so it belongs under Einstellungen
     * rather than at the head of the rail where upstream emits it.
     *
     * `/page` is the create form; a content page's own entry is `/<slug>` and
     * stays in the main block. A consultation that named a page "page" would
     * pull its entry down here, which is a cosmetic miss on a name nobody is
     * likely to use.
     */
    private function navRankFor(string $href): int
    {
        $path = rtrim((string)parse_url($href, PHP_URL_PATH), '/');

        if ($this->pathEndsWithSegment($path, 'admin')) {
            return 0;
        }
        if ($this->pathEndsWithSegment($path, 'page')) {
            return 1;
        }

        return 2;
    }

    /**
     * The href of the entry that should be marked active: the longest one whose
     * path prefixes the current path, so /admin/list beats /admin. Logout is
     * never an active destination.
     *
     * Takes a flat list rather than a DOM, because the winner has to be decided
     * across the *whole* rail at once. The nav and the menus are two separate
     * DOM passes, and resolving inside each of them independently would let both
     * mark an entry and put two selected states on screen.
     *
     * @param list<string> $hrefs
     */
    private function resolveActiveHref(array $hrefs): ?string
    {
        $request = \Yii::$app?->getRequest();
        if (!$request instanceof \yii\web\Request) {
            return null;
        }

        $current = parse_url($request->getUrl(), PHP_URL_PATH);
        if (!is_string($current) || $current === '') {
            return null;
        }
        $current = rtrim($current, '/');

        $best = null;
        foreach ($hrefs as $href) {
            $path = parse_url($href, PHP_URL_PATH);
            if (!is_string($path) || $this->pathHasSegment($path, 'logout')) {
                continue;
            }
            $path    = rtrim($path, '/');
            $matches = ($path === '') ? ($current === '') : ($current === $path || str_starts_with($current, $path . '/'));
            if ($matches && ($best === null || strlen($path) > strlen($best['path']))) {
                $best = ['path' => $path, 'href' => $href];
            }
        }

        return $best['href'] ?? null;
    }

    /**
     * Every `href` in a fragment, in document order. Used to decide the active
     * entry before either fragment is decorated.
     *
     * @return list<string>
     */
    private function hrefsIn(string $html): array
    {
        $doc = $this->loadFragment($html);
        if ($doc === null) {
            return [];
        }

        $hrefs   = [];
        $anchors = $this->query(new \DOMXPath($doc), '//a[@href]');
        if ($anchors === null) {
            return [];
        }

        foreach ($anchors as $anchor) {
            if ($anchor instanceof \DOMElement) {
                $hrefs[] = $anchor->getAttribute('href');
            }
        }

        return $hrefs;
    }

    /**
     * Mark the selected entry inside the rail's menus.
     *
     * The menus arrive as finished markup from upstream views and were being
     * emitted untouched, so the Neuigkeiten and PDF entries never took a
     * selected state: on `/std/activitylog` the rail showed nothing selected at
     * all, because the only entry for that page lives in a menu rather than in
     * the nav.
     *
     * `$activeHref` is decided once for the whole rail by the caller, so this
     * marks at most one entry and only when the nav did not already win it.
     */
    private function markActiveMenus(string $html, ?string $activeHref): string
    {
        if ($html === '' || $activeHref === null) {
            return $html;
        }

        $doc = $this->loadFragment($html);
        if ($doc === null) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $roots = $this->query($xpath, '//div[@id="modernNavRoot"]');
        if ($roots === null) {
            return $html;
        }
        $root = $roots->item(0);
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $listItems = $this->query($xpath, './/li[a]', $root);
        if ($listItems === null) {
            return $html;
        }

        foreach ($listItems as $li) {
            if (!$li instanceof \DOMElement) {
                continue;
            }
            $anchors = $this->query($xpath, './/a', $li);
            $anchor  = $anchors?->item(0);
            if (!$anchor instanceof \DOMElement || $anchor->getAttribute('href') !== $activeHref) {
                continue;
            }

            $classes = array_filter(preg_split('/\s+/', trim($li->getAttribute('class'))) ?: []);
            $classes[] = 'modernRailActive';
            $li->setAttribute('class', implode(' ', $classes));
            break;
        }

        return $this->innerHtml($root);
    }

    /**
     * Glyphicons, because Bootstrap 3 ships them and upstream already uses them
     * for the create button and search. Matched on the resolved route rather
     * than the label, so it survives translation.
     */
    private function navIconFor(\DOMElement $anchor): string
    {
        if ($anchor->getAttribute('id') === 'homeLink') {
            return 'home';
        }

        $path = (string)parse_url($anchor->getAttribute('href'), PHP_URL_PATH);

        return match (true) {
            $this->pathHasSegment($path, 'logout') => 'log-out',
            $this->pathHasSegment($path, 'login') => 'log-in',
            $this->pathHasSegment($path, 'myaccount') => 'user',
            // 4.18's language picker (`/user/setlanguage`); its label is a flag.
            $this->pathHasSegment($path, 'setlanguage') => 'globe',
            $this->pathHasSegment($path, 'admin') && $this->pathHasSegment($path, 'list') => 'list-alt',
            $this->pathHasSegment($path, 'admin') => 'cog',
            $this->pathHasSegment($path, 'motion') => 'list-alt',
            $this->pathHasSegment($path, 'page') => 'file',
            default => 'menu-right',
        };
    }

    /**
     * Drop the empty stylesheet link upstream emits on every plugin-layout page.
     *
     * `views/layouts/main.php:92` echoes `<link rel="stylesheet" href="...">`
     * unconditionally, but line 23 sets `$mainCssFile = null` for a
     * `layout-plugin-*` layout, because the stylesheet is registered as an asset
     * bundle instead. The result is `href=""`, which the browser resolves to the
     * current URL: every page requests its own HTML a second time as a
     * render-blocking stylesheet.
     *
     * That delays first paint, and while the browser holds the previous frame
     * you see the old page and the new one at once. Removing the element from
     * `endOfHead` is the earliest a plugin can reach it, since the tag is
     * already in the output by the time any hook runs.
     *
     * This is the one script that stays inline: it must run in the head,
     * before first paint, and an external file there would itself block
     * rendering. Everything else lives in `assets/shared/modern-layout.js`.
     */
    public function endOfHead(string $before): string
    {
        $script = '<script>' .
                  'document.querySelectorAll(\'link[rel="stylesheet"][href=""]\')' .
                  '.forEach(function (link) { link.remove(); });' .
                  '</script>';

        return $before . $script;
    }

    /**
     * The top bar. The wordmark moved into the rail, so this carries search,
     * which is where the reference puts it.
     */
    public function logoRow(string $before): string
    {
        $out = '<header class="logoRow modernTopbar" role="banner">';

        if ($this->consultation) {
            $out .= '<div class="modernTopbarSearch">';
            $out .= Html::beginForm(UrlHelper::createUrl('consultation/search'), 'post', [
                'class'           => 'modernSearchForm',
                'aria-labelledby' => 'modernSearchLabel',
            ]);
            $out .= '<label for="modernSearchQuery" class="sr-only" id="modernSearchLabel">' .
                    \Yii::t('con', 'sb_search') . '</label>';
            $out .= '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>';
            $out .= '<input type="text" class="form-control query" name="query" id="modernSearchQuery" required ' .
                    'placeholder="' . Html::encode(\Yii::t('con', 'sb_search_query')) . '">';
            $out .= Html::endForm();
            $out .= '</div>';
        }

        $out .= '</header>';

        return $out;
    }

    /**
     * Suppress the sidebar search box. `views/consultation/sidebar.php:59`
     * pushes this into `menusHtml`, which the rail renders, and the top bar
     * already carries search.
     */
    public function getSearchForm(string $before): string
    {
        return '';
    }

    /**
     * The right sidebar is gone. Its contents, `menusHtml` and the
     * `preSidebarHtml`/`postSidebarHtml` around it, are rendered in the rail
     * by beginPage().
     */
    public function renderSidebar(string $before): string
    {
        return '';
    }

    /**
     * Consultation warnings, and a horizontal nav that only appears below the
     * rail's breakpoint.
     *
     * No breadcrumbs. Upstream's trail is always "<consultation> / <page>", and
     * both halves are already on screen: the consultation is the rail's
     * wordmark subtitle, and the page is the h1 immediately below. It restated
     * two things and located nothing.
     *
     * The mobile nav is duplicated here rather than left to upstream's
     * `sidebarSmall` navbar, because that one is rendered by
     * `views/consultation/index.php` and the amendment view only. Admin
     * screens would otherwise have no navigation at all on a phone once the
     * rail is hidden.
     */
    public function beforeContent(string $before): string
    {
        $out = '<section class="navwrap modernMobileNav">';
        $out .= '<nav role="navigation" class="pos" id="mainmenu">';
        $out .= '<h6 class="unsichtbar">' . \Yii::t('base', 'menu_main') . ':</h6>';
        $out .= '<div class="navigation nav-fallback clearfix">';
        // Resolved over the navbar alone, not the union the rail uses: the
        // mobile bar carries no menus, so an entry that only exists in one
        // cannot be marked here and must not steal the marker from the navbar.
        // Icons on, same map as the rail. This bar is the only navigation below
        // the breakpoint and the only one at any width on the Antragsliste, so
        // the two should not disagree about what an entry looks like.
        $navHtml = $this->upstreamNavigationHtml();
        $mobileNav = $this->decorateNav(
            $navHtml,
            withIcons: true,
            split: false,
            activeHref: $this->resolveActiveHref($this->hrefsIn($navHtml))
        )['main'];
        $out .= $this->stripNavAnchorIds($mobileNav);
        $out .= '</div></nav>';
        $out .= '</section>';

        if ($this->consultation) {
            $warnings = array_merge(
                Layout::getSitewidePublicWarnings($this->consultation->site),
                Layout::getConsultationwidePublicWarnings($this->consultation)
            );
            if (count($warnings) > 0) {
                $out .= '<div class="alert alert-danger">';
                $out .= '<p>' . implode('</p><p>', $warnings) . '</p>';
                $out .= '</div>';
            }
        }

        return $out;
    }

    /**
     * Imprint, privacy and the upstream attribution.
     *
     * Shared by the rail and the page footer. The Antragsgrün link preserves
     * upstream attribution; the source link identifies this modified source
     * distribution for AGPL section 13.
     */
    private function legalLinksHtml(bool $withIds = false): string
    {
        $out = '';

        if (!defined('INSTALLING_MODE') || INSTALLING_MODE !== true) {
            $routeParams = [];
            if ($this->consultation) {
                $routeParams['consultationPath'] = $this->consultation->urlPath;
            }
            $legalLink = UrlHelper::createUrl(array_merge(
                ['/pages/show-page', 'pageSlug' => 'legal'],
                $routeParams
            ));
            $privacyLink = UrlHelper::createUrl(array_merge(
                ['/pages/show-page', 'pageSlug' => 'privacy'],
                $routeParams
            ));

            $legalOptions = ['class' => 'legal'];
            $privacyOptions = ['class' => 'privacy'];
            if ($withIds) {
                $legalOptions['id'] = 'legalLink';
                $privacyOptions['id'] = 'privacyLink';
            }
            $out .= Html::a(\Yii::t('base', 'imprint'), $legalLink, $legalOptions);
            $out .= Html::a(\Yii::t('base', 'privacy_statement'), $privacyLink, $privacyOptions);
        }

        $ariaVersion = str_replace('%VERSION%', ANTRAGSGRUEN_VERSION, \Yii::t('base', 'aria_version_hint'));
        $out .= '<span class="version">';
        $out .= Html::a('Antragsgrün', 'https://antragsgruen.de/', [
            'aria-label' => \Yii::t('base', 'aria_antragsgruen'),
        ]) . ', Version ';
        $out .= Html::a(Html::encode(ANTRAGSGRUEN_VERSION), ANTRAGSGRUEN_HISTORY_URL, ['aria-label' => $ariaVersion]);
        $out .= $this->sourceOfferHtml();
        $out .= '</span>';

        return $out;
    }

    /**
     * The DOM pass: markup adjustments no hook can reach, in
     * `assets/shared/modern-layout.js`.
     *
     * Emitted here rather than registered as a bundle script on purpose: Yii
     * writes bundle scripts at `endBody()`, after upstream's own scripts,
     * while this hook output sits before them. The transforms have always run
     * at this position, so it is kept.
     *
     * The URL comes from SharedAssets, which every layout built on this one
     * depends on, with the same mtime cache-busting as the stylesheets.
     * Translated labels travel as data attributes, which Html::jsFile()
     * encodes.
     */
    public function endPage(string $before): string
    {
        $view = \Yii::$app?->getView();
        $file = __DIR__ . '/assets/shared/modern-layout.js';
        if (!$view instanceof \yii\web\View || !file_exists($file)) {
            return $before;
        }

        $baseUrl = SharedAssets::register($view)->baseUrl;

        return $before . Html::jsFile($baseUrl . '/modern-layout.js?v=' . filemtime($file), [
            'data-feed-open'           => $this->t('feed_open_new_tab'),
            'data-feed-copy'           => $this->t('feed_copy_address'),
            'data-select-row'          => $this->t('select_row'),
            'data-select-row-untitled' => $this->t('select_row_untitled'),
        ]);
    }

    /**
     * The page footer. Rendered for every width, but hidden by the stylesheet
     * above the rail breakpoint, where the rail carries the same links under
     * Logout. Below that breakpoint the rail is not rendered at all, so this is
     * the only copy.
     */
    public function footerLine(string $before): string
    {
        return '<footer class="footer modernFooter" aria-label="' .
               Html::encode(\Yii::t('base', 'aria_footer')) . '"><div class="container">' .
               $this->legalLinksHtml(true) .
               '</div></footer>';
    }

    /**
     * Motions whose upstream data table kept its status row in this request.
     *
     * @var array<int, true>
     */
    private array $motionsWithVisibleStatus = [];

    /**
     * Record whether upstream shows this motion's status. Data is unchanged.
     *
     * `_view_motiondata.php` decides that from the consultation's
     * `motiondataMode` (none, all, or only non-default statuses) before it
     * calls this hook, and `view.php` calls `beforeMotionView()` afterwards.
     * Reading the decision here means the summary obeys the admin setting
     * without duplicating upstream's rule.
     *
     * @param array<int, array<string, mixed>> $motionData
     * @return array<int, array<string, mixed>>
     */
    public function getMotionViewData(array $motionData, Motion $motion): array
    {
        foreach ($motionData as $row) {
            if (($row['rowClass'] ?? null) === 'statusRow') {
                $this->motionsWithVisibleStatus[(int)$motion->id] = true;
                break;
            }
        }

        return $motionData;
    }

    /**
     * Add a server-rendered status summary without replacing the upstream
     * status row or its configured workflow label, and only where upstream
     * shows the status at all.
     */
    public function beforeMotionView(string $before, Motion $motion): string
    {
        if (!isset($this->motionsWithVisibleStatus[(int)$motion->id])) {
            return $before;
        }

        return $before . $this->statusPresenter()->renderDetail($motion);
    }

    public function getConsultationMotionLineContent(string $before, Motion $motion): string
    {
        return $this->statusPresenter()->appendListStatus($before, $motion);
    }

    public function getConsultationAmendmentLineContent(string $before, Amendment $amendment): string
    {
        return $this->statusPresenter()->appendAmendmentListStatus($before, $amendment);
    }

    /**
     * Keep all upstream amendment metadata intact. The summary is additive.
     *
     * @param array<int, array<string, mixed>> $amendmentData
     * @return array<int, array<string, mixed>>
     */
    public function getAmendmentViewData(array $amendmentData, Amendment $amendment): array
    {
        foreach ($amendmentData as $index => $row) {
            if (($row['rowClass'] ?? null) !== 'statusRow') {
                continue;
            }

            array_splice($amendmentData, $index + 1, 0, [[
                'rowClass' => 'modernStatusExplanationRow',
                'title' => $this->t('status_heading'),
                'content' => $this->statusPresenter()->renderDetail($amendment),
            ]]);
            break;
        }

        return $amendmentData;
    }
}
