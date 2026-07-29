<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\components\UrlHelper;
use app\models\db\{Amendment, IComment, Motion};
use app\models\db\repostory\MotionRepository;
use app\models\layoutHooks\{Hooks, Layout};
use yii\helpers\Html;

/**
 * Structural overrides for the SPD layout.
 *
 * Only the hooks that need different markup are implemented. Everything not
 * listed here falls through to the base Hooks implementation, which keeps this
 * class small and keeps upstream template changes flowing through.
 *
 * G3 in the PRD is "the UI is not recognisably Antragsgruen", so the wordmark
 * changes here while the footer keeps explicit upstream attribution. The
 * separate AGPL section 13 source offer for this modified version remains an
 * external publication gate until its canonical repository URL is known.
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
 * `#page`, and the SCSS positions it absolutely against that relative
 * containing block. This works on every page regardless of whether the
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
     * The upstream navbar is stateful hook output. Render it once per request,
     * then derive the desktop and mobile presentations from the same markup.
     */
    private ?string $navbarHtml = null;

    /**
     * Screens that are a dense data table rather than a document and need the
     * table-specific presentation adapter inside the normal responsive shell.
     *
     * Only the Antragsliste for now. Route comes from `config/urls.php:58`,
     * which maps `<consultation>/admin/list` to `admin/motion-list/index`.
     */
    private function isTableView(): bool
    {
        $controller = \Yii::$app->controller;

        return $controller !== null && $controller->getUniqueId() === 'admin/motion-list';
    }

    private function navbarHtml(): string
    {
        if ($this->navbarHtml === null) {
            $this->navbarHtml = Layout::getStdNavbarHeader();
        }

        return $this->navbarHtml;
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
        // `body:has(.spdTableView)`. It changes the dense table, not the shell:
        // tablet and desktop keep the persistent rail like every other route.
        // A class cannot be put on <body> from here: main.php has already
        // emitted the opening <body> tag by the time any of these hooks run.
        $out = '';
        if ($this->isTableView()) {
            $out .= '<span class="spdTableView" hidden></span>';
        }

        $home = Html::encode(UrlHelper::homeUrl());

        $out .= '<nav class="spdRail" id="spdRail" role="navigation" aria-label="' .
                Html::encode(\Yii::t('base', 'menu_main')) . '">';

        $out .= '<div class="spdRailBrand">';
        if ($this->consultation && $this->consultation->getSettings()->logoUrl) {
            $out .= '<a href="' . $home . '" rel="home" class="spdRailLogo" title="' .
                    Html::encode(\Yii::t('base', 'home_back')) . '">' .
                    $this->layout->getLogoStr() . '</a>';
        }
        $out .= '<div class="spdRailBrandText">';
        $out .= '<a href="' . $home . '" rel="home" class="spdRailWordmark">Antragsplatform</a>';
        if ($this->consultation) {
            $out .= '<span class="spdRailConsultation">' . Html::encode($this->consultation->title) . '</span>';
        }
        $out .= '</div>';
        $out .= '<button type="button" class="spdRailDrawerClose" aria-label="' .
                Html::encode(\Yii::t('base', 'menu_main') . ' schließen') . '">' .
                '<span class="spdLucideIcon spdLucideX" aria-hidden="true"></span></button>';
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
        $navHtml    = $this->navbarHtml();
        $menusHtml  = $this->wrapMotionTitleLabels($this->sidebarSlotsHtml($menus));
        $activeHref = $this->resolveActiveHref(
            array_merge($this->hrefsIn($navHtml), $this->hrefsIn($menusHtml))
        );

        $nav = $this->decorateNav($navHtml, true, true, $activeHref);
        $menusHtml = $this->markActiveMenus($menusHtml, $nav['activeMarked'] ? null : $activeHref);

        $out .= '<div class="spdRailScroll">';
        if ($create !== '') {
            $out .= '<div class="spdRailPrimary">' . $create . '</div>';
        }
        $out .= '<div class="spdRailNav">' . $nav['main'] . '</div>';
        if ($menusHtml !== '') {
            $out .= '<div class="spdRailMenus">' . $menusHtml . '</div>';
        }
        $out .= '</div>';

        // Pinned to the bottom: administration and account first, then logout
        // again separated from those, so it is not hit by accident.
        {
            $out .= '<div class="spdRailFooter spdRailNav">';
            if ($nav['secondary'] !== '') {
                $out .= '<div class="spdRailSecondary">' . $nav['secondary'] . '</div>';
            }
            if ($nav['logout'] !== '') {
                $out .= '<div class="spdRailLogout">' . $nav['logout'] . '</div>';
            }
            $out .= '<div class="spdRailLegal" role="contentinfo" aria-label="' .
                    Html::encode(\Yii::t('base', 'aria_footer')) . '">' .
                    $this->legalLinksHtml() . '</div>';
            $out .= '</div>';
        }

        $out .= '</nav>';

        return $out;
    }

    /**
     * Preserve the three extension slots in the same order as upstream's
     * `StdHooks::renderSidebar()`.
     *
     * @param list<string> $menus
     */
    private function sidebarSlotsHtml(array $menus): string
    {
        return $this->layout->preSidebarHtml .
               implode('', $menus) .
               $this->layout->postSidebarHtml;
    }

    /**
     * Give recent Antrag labels their own clamping box.
     *
     * Upstream renders the document icon and title text as direct children of
     * one anchor. The rail needs the icon and a two-line title to align as two
     * flex items, so only the title content is wrapped. This is a presentational
     * transformation of upstream-owned markup; destinations, text, order,
     * permission decisions and accessible names remain unchanged.
     */
    private function wrapMotionTitleLabels(string $html): string
    {
        $doc = $this->loadFragment($html);
        if ($doc === null) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $root  = $this->xpathQuery($xpath, '//div[@id="spdNavRoot"]')->item(0);
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $anchors = $this->xpathQuery(
            $xpath,
            './/section[contains(concat(" ", normalize-space(@class), " "), " sidebar-box-motions ")]' .
            '//li[contains(concat(" ", normalize-space(@class), " "), " motionTitle ")]/a',
            $root
        );
        foreach ($anchors as $anchor) {
            if (!$anchor instanceof \DOMElement ||
                $this->xpathQuery(
                    $xpath,
                    './span[contains(concat(" ", normalize-space(@class), " "), " spdRailMotionTitleLabel ")]',
                    $anchor
                )->length > 0
            ) {
                continue;
            }

            $label = $doc->createElement('span');
            $label->setAttribute('class', 'spdRailMotionTitleLabel');
            foreach (iterator_to_array($anchor->childNodes) as $child) {
                if ($child instanceof \DOMElement &&
                    in_array('glyphicon', preg_split('/\s+/', trim($child->getAttribute('class'))) ?: [], true)
                ) {
                    continue;
                }
                $label->appendChild($child);
            }
            if ($label->hasChildNodes()) {
                $anchor->appendChild($label);
            }
        }

        return $this->innerHtml($root);
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

        $controller = \Yii::$app->controller;
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
            \Yii::warning('spd_layout: consultation menus not rendered: ' . $e->getMessage(), 'spd_layout');
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
     * @return array{main: string, secondary: string, logout: string, activeMarked: bool}
     */
    private function decorateNav(
        string $navHtml,
        bool $withIcons,
        bool $split,
        ?string $activeHref
    ): array {
        $empty = ['main' => $navHtml, 'secondary' => '', 'logout' => '', 'activeMarked' => false];

        $doc = $this->loadFragment($navHtml);
        if ($doc === null) {
            return $empty;
        }

        $xpath = new \DOMXPath($doc);
        $root  = $this->xpathQuery($xpath, '//div[@id="spdNavRoot"]')->item(0);
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        foreach ($this->xpathQuery($xpath, './/a[@aria-current]', $root) as $anchor) {
            if ($anchor instanceof \DOMElement) {
                $anchor->removeAttribute('aria-current');
            }
        }

        /** @var array{secondary: list<array{0: int, 1: string}>, logout: list<array{0: int, 1: string}>} $extracted */
        $extracted    = ['secondary' => [], 'logout' => []];
        $activeMarked = false;

        foreach (iterator_to_array($this->xpathQuery($xpath, './/li', $root)) as $li) {
            if (!$li instanceof \DOMElement) {
                continue;
            }

            $classes = preg_split('/\s+/', trim($li->getAttribute('class'))) ?: [];
            $classes = array_values(array_filter($classes, static fn(string $c): bool => $c !== '' && $c !== 'active'));

            $anchor = $this->xpathQuery($xpath, './/a', $li)->item(0);
            if (!$anchor instanceof \DOMElement) {
                $li->setAttribute('class', implode(' ', $classes));
                continue;
            }

            $href = $anchor->getAttribute('href');

            if (!$activeMarked && $activeHref !== null && $href === $activeHref) {
                $classes[] = 'spdRailActive';
                $anchor->setAttribute('aria-current', 'page');
                $activeMarked = true;
            } else {
                $anchor->removeAttribute('aria-current');
            }
            $li->setAttribute('class', implode(' ', $classes));

            if ($withIcons &&
                $this->xpathQuery($xpath, './/span[contains(@class, "glyphicon")]', $anchor)->length === 0
            ) {
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
            'activeMarked' => $activeMarked,
        ];
    }

    /**
     * Execute an internal constant XPath and make an invalid expression a
     * programmer error instead of letting `false` leak into DOM operations.
     *
     * @return \DOMNodeList<\DOMNameSpaceNode|\DOMNode>
     */
    private function xpathQuery(
        \DOMXPath $xpath,
        string $expression,
        ?\DOMNode $contextNode = null
    ): \DOMNodeList {
        $nodes = $xpath->query($expression, $contextNode);
        if ($nodes === false) {
            throw new \LogicException('Invalid internal XPath: ' . $expression);
        }

        return $nodes;
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
            '<?xml encoding="UTF-8"?><div id="spdNavRoot">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousMode);

        return $loaded ? $doc : null;
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

        if (str_contains($path, '/logout')) {
            return 'logout';
        }
        if (str_contains($path, '/myaccount') ||
            str_ends_with($path, '/admin') ||
            str_ends_with($path, '/page')) {
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

        if (str_ends_with($path, '/admin')) {
            return 0;
        }
        if (str_ends_with($path, '/page')) {
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
    private function resolveActiveHref(array $hrefs, ?string $requestUrl = null): ?string
    {
        if ($requestUrl === null) {
            $request = \Yii::$app->get('request');
            if (!$request instanceof \yii\web\Request) {
                return null;
            }
            $requestUrl = $request->getUrl();
        }

        $current = parse_url($requestUrl, PHP_URL_PATH);
        if (!is_string($current) || $current === '') {
            return null;
        }
        $current = rtrim($current, '/');

        $best = null;
        foreach ($hrefs as $href) {
            $path = parse_url($href, PHP_URL_PATH);
            if (!is_string($path)) {
                continue;
            }
            $path    = rtrim($path, '/');
            if (str_contains($path, '/logout') || str_ends_with($path, '/page')) {
                continue;
            }
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

        $hrefs = [];
        $xpath = new \DOMXPath($doc);
        foreach ($this->xpathQuery($xpath, '//a[@href]') as $anchor) {
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
     * `$activeHref` is decided once for the whole rail by the caller. A null
     * value means the navbar already owns the marker, so inherited menu markers
     * are cleared without adding another one.
     */
    private function markActiveMenus(string $html, ?string $activeHref): string
    {
        if ($html === '') {
            return $html;
        }

        $doc = $this->loadFragment($html);
        if ($doc === null) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $root  = $this->xpathQuery($xpath, '//div[@id="spdNavRoot"]')->item(0);
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        foreach ($this->xpathQuery($xpath, './/a[@aria-current]', $root) as $anchor) {
            if ($anchor instanceof \DOMElement) {
                $anchor->removeAttribute('aria-current');
            }
        }

        foreach ($this->xpathQuery($xpath, './/li[a]', $root) as $li) {
            if (!$li instanceof \DOMElement) {
                continue;
            }
            $anchor = $this->xpathQuery($xpath, './/a', $li)->item(0);
            if (!$anchor instanceof \DOMElement) {
                continue;
            }

            $classes = preg_split('/\s+/', trim($li->getAttribute('class'))) ?: [];
            $classes = array_values(array_filter(
                $classes,
                static fn(string $class): bool => $class !== '' &&
                                                  $class !== 'active' &&
                                                  $class !== 'spdRailActive'
            ));
            if ($activeHref === null || $anchor->getAttribute('href') !== $activeHref) {
                $li->setAttribute('class', implode(' ', $classes));
                continue;
            }

            $classes[] = 'spdRailActive';
            $li->setAttribute('class', implode(' ', $classes));
            $anchor->setAttribute('aria-current', 'page');
            $activeHref = null;
        }

        return $this->innerHtml($root);
    }

    /**
     * Keep upstream's Glyphicon class vocabulary in the markup; the stylesheet
     * maps every current runtime class to Lucide. Match on the resolved route
     * rather than the label, so the choice survives translation.
     */
    private function navIconFor(\DOMElement $anchor): string
    {
        if ($anchor->getAttribute('id') === 'homeLink') {
            return 'home';
        }

        $path = (string)parse_url($anchor->getAttribute('href'), PHP_URL_PATH);
        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn(string $segment): bool => $segment !== ''
        ));
        $endsWith = static function (array $suffix) use ($segments): bool {
            return count($segments) >= count($suffix) &&
                array_slice($segments, -count($suffix)) === $suffix;
        };

        return match (true) {
            $endsWith(['logout'])       => 'log-out',
            $endsWith(['login'])        => 'log-in',
            $endsWith(['myaccount'])    => 'user',
            $endsWith(['admin', 'list']) => 'list-alt',
            $endsWith(['admin'])        => 'cog',
            in_array('motion', $segments, true) => 'list-alt',
            $endsWith(['page'])         => 'file',
            default                     => 'menu-right',
        };
    }

    /**
     * Publish the SPD mark as the complete browser and installed-app icon set.
     *
     * The base layout otherwise emits Antragsgrün's green favicon family. The
     * selected layout asset bundle is already registered before the head is
     * rendered, so its published base URL is the stable public home for these
     * files. A shared mtime version keeps browsers from retaining an older
     * icon after a deployment.
     */
    public function favicons(string $before): string
    {
        $view = \Yii::$app->getView();
        if (!$view instanceof \yii\web\View) {
            throw new \RuntimeException('SPD layout assets require a web view.');
        }
        $baseUrl = Assets::register($view)->baseUrl;
        $assetDirectory = __DIR__ . '/assets';
        $iconFiles = [
            'app-icon-512.png',
            'app-icon-192.png',
            'apple-touch-icon.png',
            'favicon-32x32.png',
            'favicon-16x16.png',
            'favicon.ico',
            'safari-pinned-tab.svg',
            'site.webmanifest',
        ];
        $version = 0;
        foreach ($iconFiles as $iconFile) {
            $modified = filemtime($assetDirectory . '/' . $iconFile);
            if ($modified !== false) {
                $version = max($version, $modified);
            }
        }

        $assetUrl = static fn(string $file): string => Html::encode(
            $baseUrl . '/' . $file . '?v=' . $version
        );

        return implode("\n", [
            '<link rel="apple-touch-icon" sizes="180x180" href="' .
            $assetUrl('apple-touch-icon.png') . '">',
            '<link rel="icon" type="image/png" sizes="32x32" href="' .
            $assetUrl('favicon-32x32.png') . '">',
            '<link rel="icon" type="image/png" sizes="16x16" href="' .
            $assetUrl('favicon-16x16.png') . '">',
            '<link rel="shortcut icon" href="' . $assetUrl('favicon.ico') . '">',
            '<link rel="mask-icon" href="' . $assetUrl('safari-pinned-tab.svg') .
            '" color="#E3000F">',
            '<link rel="manifest" href="' . $assetUrl('site.webmanifest') . '">',
            '<meta name="theme-color" content="#E3000F">',
            '<meta name="msapplication-TileColor" content="#E3000F">',
            '<meta name="msapplication-TileImage" content="' .
            $assetUrl('app-icon-192.png') . '">',
        ]);
    }

    /**
     * The mark punched into the middle of the two-factor QR code.
     *
     * `SecondFactorAuthentication::createQrCode()` asks for this and falls back
     * to Antragsgrün's own apple-touch-icon, whose green and magenta reads as
     * another party's branding on an SPD site. It is not an attribution
     * requirement. The footer link is upstream attribution, not the separate
     * AGPL section 13 source offer for this modified version.
     *
     * The file is the SPD wordmark from Wikimedia (public domain for copyright,
     * trademarked as a mark), rasterised white on #E3000F with padding. The QR
     * builder downsizes it to 50x50 and punches out the modules behind it, so
     * it is deliberately a plain block rather than fine artwork.
     */
    public function squareLogoPath(?string $before): ?string
    {
        return __DIR__ . '/assets/qr-logo.png';
    }

    /**
     * Add one server-rendered status summary from the authoritative Motion.
     *
     * The asset moves this block directly below the motion heading. Without
     * JavaScript it remains visible immediately before the motion text.
     */
    public function beforeMotionView(string $before, Motion $motion): string
    {
        return $before . MotionStatusPresenter::renderDetail($motion);
    }

    /**
     * Append the same normalized status to consultation motion cards.
     */
    public function getConsultationMotionLineContent(
        string $before,
        Motion $motion
    ): string {
        return MotionStatusPresenter::appendListStatus($before, $motion);
    }

    /**
     * Append the same authoritative status treatment to amendment rows.
     */
    public function getConsultationAmendmentLineContent(
        string $before,
        Amendment $amendment
    ): string {
        return MotionStatusPresenter::appendAmendmentListStatus($before, $amendment);
    }

    /**
     * Keep upstream motion metadata intact. It contains configured labels and
     * links that are part of the authoritative legal record.
     *
     * @param array<int, array<string, mixed>> $motionData
     * @return array<int, array<string, mixed>>
     */
    public function getMotionViewData(array $motionData, Motion $motion): array
    {
        return $motionData;
    }

    /**
     * Keep upstream amendment metadata intact for the same reason as motions.
     * The SPD summary is additive and does not replace the source row.
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
                'rowClass' => 'spdStatusExplanationRow',
                'title' => 'Aktueller Stand',
                'content' => MotionStatusPresenter::renderDetail($amendment),
            ]]);
            break;
        }

        return $amendmentData;
    }

    /**
     * Drop the empty stylesheet link upstream emits on every plugin-layout page
     * and repair two labels rendered before a consultation can select this
     * plugin's translation variant.
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
     */
    public function endOfHead(string $before): string
    {
        // Upstream hides the source textarea in CSS before CKEditor is
        // initialized. With JavaScript disabled the replacement remains a
        // plain, non-editable div, so motion creation cannot be completed.
        // Keep this fallback inside <noscript>: the enhanced editor remains
        // untouched, while the server-rendered form regains its native input.
        $noScript = '<noscript><style>' .
                    '#motionEditForm .wysiwyg-textarea>textarea' .
                    '{display:block!important;width:100%;min-height:10rem;' .
                    'padding:10px 12px;border:1px solid #707070;border-radius:4px;}' .
                    '#motionEditForm .wysiwyg-textarea>.texteditor' .
                    '{display:none!important;}' .
                    '</style></noscript>';

        $script = '<script>' .
                  'document.querySelectorAll(\'link[rel="stylesheet"][href=""]\')' .
                  '.forEach(function (link) { link.remove(); });' .
                  'document.addEventListener("DOMContentLoaded", function () {' .
                  'document.querySelectorAll("h1").forEach(function (heading) {' .
                  'if (heading.textContent.trim() === "Antragsgrün einrichten") {' .
                  'heading.textContent = "Antragsplattform einrichten";' .
                  '}' .
                  '});' .
                  'document.querySelectorAll("a").forEach(function (link) {' .
                  'if (link.textContent.trim() === "Antragsgrün-Startseite") {' .
                  'link.textContent = "Antragsplatform-Startseite";' .
                  '}' .
                  '});' .
                  'document.title = document.title' .
                  '.replace("Antragsgrün einrichten", "Antragsplattform einrichten");' .
                  '});' .
                  '</script>';

        return $before . $noScript . $script;
    }

    /**
     * The top bar. The wordmark moved into the rail, so this carries the
     * compact drawer trigger followed by search.
     */
    public function logoRow(string $before): string
    {
        $out = '<header class="logoRow spdTopbar" role="banner">';

        if ($this->consultation) {
            $menuName = \Yii::t('base', 'menu_main');
            $out .= '<button type="button" class="spdMobileMenuTrigger" aria-controls="spdRail" ' .
                    'aria-expanded="false" aria-label="' . Html::encode($menuName . ' öffnen') . '" ' .
                    'data-menu-name="' . Html::encode($menuName) . '">' .
                    '<span class="glyphicon glyphicon-menu-hamburger" aria-hidden="true"></span>' .
                    '<span class="sr-only spdMobileMenuLabel">' . Html::encode($menuName) . '</span></button>';
            $out .= '<div class="spdTopbarSearch">';
            $out .= Html::beginForm(UrlHelper::createUrl('consultation/search'), 'post', [
                'class'           => 'spdSearchForm',
                'aria-labelledby' => 'spdSearchLabel',
            ]);
            $out .= '<label for="spdSearchQuery" class="sr-only" id="spdSearchLabel">' .
                    \Yii::t('con', 'sb_search') . '</label>';
            $out .= '<span class="glyphicon glyphicon-search" aria-hidden="true"></span>';
            $out .= '<input type="text" class="form-control query" name="query" id="spdSearchQuery" required ' .
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
     * The right sidebar is gone. Its contents are rendered in the rail by
     * beginPage(), from the same `menusHtml` this hook would have used.
     */
    public function renderSidebar(string $before): string
    {
        return '';
    }

    /**
     * Suppress upstream's "Dein Antragsgruen" promo box in the sidebar.
     *
     * StdHooks::getAntragsgruenAd() renders an advert for antragsgruen.de.
     * Returning an empty string removes it at the source, which is cleaner
     * than hiding it with display:none and keeps it out of the accessibility
     * tree and out of the page for screen readers.
     *
     * This is presentation only. Upstream attribution stays in the footer; the
     * source offer for this modified version is a separate publication gate.
     */
    public function getAntragsgruenAd(string $before): string
    {
        return '';
    }

    /**
     * Consultation warnings and the compact control for the canonical rail.
     *
     * No breadcrumbs. Upstream's trail is always "<consultation> / <page>", and
     * both halves are already on screen: the consultation is the rail's
     * wordmark subtitle, and the page is the h1 immediately below. It restated
     * two things and located nothing.
     *
     * The permission-filtered rail is rendered once by beginPage(). Below the
     * rail breakpoint the top-bar trigger discloses that same element as a
     * drawer. Its existing primary-action node is moved into this slot, so no
     * route, ID, form, or page-action markup is cloned.
     */
    public function beforeContent(string $before): string
    {
        $out = '<section class="navwrap spdMobileNav">';
        $out .= '<div class="spdMobileActionRow">';
        $out .= '<div class="spdMobilePrimarySlot"></div>';
        $menuName = \Yii::t('base', 'menu_main');
        $out .= '</div>';
        $out .= '<button type="button" class="spdMobileBackdrop" tabindex="-1" hidden ' .
                'aria-label="' . Html::encode($menuName . ' schließen') . '"></button>';
        $out .= '</section>';

        if ($this->consultation) {
            $warnings = array_merge(
                Layout::getSitewidePublicWarnings($this->consultation->site),
                Layout::getConsultationwidePublicWarnings($this->consultation)
            );
            if (count($warnings) > 0) {
                $out .= '<div class="alert alert-danger consultationwideWarning">';
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
     * upstream attribution. The separate section 13 source offer for this
     * modified version is tracked as a publication requirement.
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
        $out .= '</span>';

        return $out;
    }

    /**
     * Split the initiator fieldset into two cards.
     *
     * `views/motion/edit_form.php` renders the whole block as one flat list of
     * rows inside `.initiatorData`, with `.contactHead` acting as a divider
     * partway down. There is no hook for that view and no wrapper to style, so
     * the two halves get wrapped client-side.
     *
     * Safe to reparent: `InitiatorForm.js` addresses every row with jQuery
     * `.find()` (descendant), and no stylesheet uses a child combinator under
     * `.initiatorData`, so the show/hide logic for the organisation and
     * resolution rows keeps working. The guard makes it idempotent, and if the
     * markup ever changes shape the script simply does nothing.
     */
    public function endPage(string $before): string
    {
        $script = <<<'JS'
<script>
(function () {
    var enhancementRoot = document.querySelector('#mainContent');
    if (!enhancementRoot) { return; }

    enhancementRoot.querySelectorAll('.initiatorData').forEach(function (data) {
        if (data.dataset.spdSplit) { return; }
        var head = data.querySelector(':scope > .contactHead');
        if (!head) { return; }
        data.dataset.spdSplit = '1';

        var nodes = Array.prototype.slice.call(data.children);
        var split = nodes.indexOf(head);
        var first = document.createElement('div');
        var second = document.createElement('div');
        first.className = 'spdFormCard spdFormCardPrimary';
        second.className = 'spdFormCard';

        nodes.slice(0, split).forEach(function (n) { first.appendChild(n); });
        nodes.slice(split).forEach(function (n) { second.appendChild(n); });
        data.appendChild(first);
        data.appendChild(second);
    });

    // `admin/consultation` writes its edit action as `std [ändern]`, with the
    // brackets as bare text nodes either side of the link. The link is styled as
    // a pill, and a pill in square brackets reads as a mistake. No selector
    // reaches a text node, so the brackets are trimmed here.
    enhancementRoot.querySelectorAll('.urlPathHolder > .shower').forEach(function (shower) {
        if (shower.dataset.spdBrackets) { return; }
        var link = shower.querySelector('a');
        if (!link) { return; }
        shower.dataset.spdBrackets = '1';

        var before = link.previousSibling;
        var after = link.nextSibling;
        if (before && before.nodeType === 3) {
            before.textContent = before.textContent.replace(/\[\s*$/, '');
        }
        if (after && after.nodeType === 3) {
            after.textContent = after.textContent.replace(/^\s*\]/, '');
        }
    });

    // House rule: an underline marks the words of a link, never its icon.
    //
    // `.btn` is a flex row here, so a `text-decoration` on a `.btn-link` is
    // drawn under every flex item, the icon included, and nothing a descendant
    // declares switches a propagated decoration back off. Giving the label an
    // element of its own lets the stylesheet underline that instead.
    //
    // Only buttons that have both an icon and a label are touched. Without an
    // icon there is nothing to protect, and the stylesheet leaves the line on
    // the button itself, so those keep their markup exactly as upstream wrote
    // it. That halves how much of the DOM this rewrites.
    var ICONS = /(^|\s)(glyphicon|fontello|caret|icon-)/;
    function wrapButtonLabels() {
        enhancementRoot.querySelectorAll('.btn-link').forEach(function (btn) {
            if (btn.dataset.spdLabel) { return; }
            if (!btn.querySelector('.glyphicon, .fontello, .caret')) { return; }
            if (!btn.textContent.trim()) { return; }
            btn.dataset.spdLabel = '1';

            Array.prototype.slice.call(btn.childNodes).forEach(function (node) {
                if (node.nodeType === 3) {
                    if (!node.textContent.trim()) { return; }
                    var span = document.createElement('span');
                    span.className = 'spdBtnLabel';
                    btn.insertBefore(span, node);
                    span.appendChild(node);
                    return;
                }

                // Text already inside an element of its own needs no wrapper,
                // only the marker. Icons and screen-reader text are skipped.
                if (node.nodeType === 1 &&
                    !ICONS.test(String(node.className)) &&
                    !node.classList.contains('sr-only')) {
                    node.classList.add('spdBtnLabel');
                }
            });
        });
    }

    // Every feed row is one bare link to an address that belongs in a reader,
    // not in this tab. Rebuilt as a name plus the two things anyone does with a
    // feed URL. There is no hook for this view, so it happens here.
    function rebuildFeedRows() {
        var FEEDS = '.feedMotions, .feedAmendments, .feedComments, .feedAll';
        var status = document.getElementById('spdFeedCopyStatus');
        if (!status && enhancementRoot.querySelector(FEEDS)) {
            status = document.createElement('span');
            status.id = 'spdFeedCopyStatus';
            status.className = 'sr-only';
            status.setAttribute('role', 'status');
            status.setAttribute('aria-live', 'polite');
            status.setAttribute('aria-atomic', 'true');
            document.body.appendChild(status);
        }

        enhancementRoot.querySelectorAll(FEEDS).forEach(function (link) {
            var row = link.closest('.title');
            if (!row || row.dataset.spdFeedRow) { return; }
            row.dataset.spdFeedRow = '1';

            // The stylesheet keys the feeds list off `.feedAll`, which is on a
            // link this function is about to delete. Mark the list itself so
            // the rules keep matching afterwards.
            var list = link.closest('.motionListStd');
            if (list) { list.classList.add('spdFeedList'); }

            var url  = link.href;
            var icon = link.querySelector('.fontello');
            var name = link.textContent.trim();

            row.textContent = '';
            if (icon) { row.appendChild(icon); }

            var label = document.createElement('span');
            label.className = 'spdFeedName';
            label.textContent = name;
            row.appendChild(label);

            row.appendChild(feedAction('a', 'spdFeedOpen', 'glyphicon-new-window',
                'Feed in neuem Tab öffnen', function (el) {
                    el.href = url;
                    el.target = '_blank';
                    el.rel = 'noopener noreferrer';
                }));

            row.appendChild(feedAction('button', 'spdFeedCopy', 'glyphicon-duplicate',
                'Feed-Adresse kopieren', function (el) {
                    el.type = 'button';
                    el.addEventListener('click', function () { copyUrl(url, el); });
                }));
        });
    }

    function feedAction(tag, cls, glyph, label, init) {
        var el = document.createElement(tag);
        el.className = 'spdFeedAction ' + cls;
        el.title = label;
        el.innerHTML = '<span class="glyphicon ' + glyph + '" aria-hidden="true"></span>' +
                       '<span class="sr-only">' + label + '</span>';
        init(el);
        return el;
    }

    function copyUrl(url, button) {
        function done() {
            var icon = button.querySelector('.glyphicon');
            var label = button.querySelector('.sr-only');
            var status = document.getElementById('spdFeedCopyStatus');

            if (icon) {
                icon.classList.remove('glyphicon-duplicate');
                icon.classList.add('glyphicon-ok');
            }
            if (label) { label.textContent = 'Feed-Adresse kopiert'; }
            button.title = 'Feed-Adresse kopiert';
            button.classList.add('spdCopied');

            if (status) {
                status.textContent = '';
                setTimeout(function () { status.textContent = 'Feed-Adresse kopiert'; }, 0);
            }

            if (button.spdCopyReset) { clearTimeout(button.spdCopyReset); }
            button.spdCopyReset = setTimeout(function () {
                button.classList.remove('spdCopied');
                button.title = 'Feed-Adresse kopieren';
                if (icon) {
                    icon.classList.remove('glyphicon-ok');
                    icon.classList.add('glyphicon-duplicate');
                }
                if (label) { label.textContent = 'Feed-Adresse kopieren'; }
                if (status) { status.textContent = ''; }
            }, 1400);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done, function () {});
            return;
        }

        // The Clipboard API needs a secure context. This keeps the button
        // working if the site is ever served over plain http on a staging box.
        var field = document.createElement('textarea');
        field.value = url;
        field.setAttribute('readonly', '');
        field.style.cssText = 'position:absolute;left:-9999px';
        document.body.appendChild(field);
        field.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        field.remove();
    }

    // Anything pointing off this host opens in its own tab. `rel` goes with it:
    // `target="_blank"` without `noopener` hands the opened page a handle on
    // this one.
    function externalLinksToNewTab(root) {
        root.querySelectorAll('a[href]').forEach(function (a) {
            if (a.dataset.spdExternal) { return; }
            if (!/^https?:/i.test(a.getAttribute('href') || '')) { return; }
            if (a.hostname === location.hostname) { return; }

            a.dataset.spdExternal = '1';
            a.target = '_blank';
            a.rel = (a.rel ? a.rel + ' ' : '') + 'noopener noreferrer';
        });
    }

    // The edit action for a content block belongs on the card's header, beside
    // the title it edits, not floated inside the body above the first line of
    // the text. Upstream puts the Redeliste equivalent in its heading already;
    // this brings the content pages in line with it.
    function editActionsToHeader() {
        enhancementRoot.querySelectorAll('.editCaller').forEach(function (btn) {
            if (btn.dataset.spdInHeader) { return; }

            var body = btn.closest('.content');
            if (!body) { return; }

            // The heading is not always the immediate previous sibling: the
            // consultation start page has a hidden mobile nav between the two.
            var header = body.previousElementSibling;
            while (header && !/^H[1-3]$/.test(header.tagName)) {
                header = header.previousElementSibling;
            }
            if (!header) { return; }

            btn.dataset.spdInHeader = '1';
            var wrapper = document.createElement('div');
            wrapper.className = 'spdEditableHeading';
            header.parentNode.insertBefore(wrapper, header);
            wrapper.appendChild(header);
            wrapper.appendChild(btn);
        });
    }

    // One permission-filtered rail serves both presentations. On compact
    // screens it becomes a drawer, while its existing primary-action node
    // moves below the top bar. Moving the node preserves IDs and handlers.
    function setupCompactNavigation() {
        var rail = document.querySelector('.spdRail');
        var shell = document.querySelector('.spdMobileNav');
        var trigger = document.querySelector('.spdMobileMenuTrigger');
        if (!rail || !shell || !trigger) { return; }

        var slot = shell.querySelector('.spdMobilePrimarySlot');
        var backdrop = shell.querySelector('.spdMobileBackdrop');
        var closeButton = rail.querySelector('.spdRailDrawerClose');
        var primary = rail.querySelector('.spdRailPrimary');
        var primaryMarker = document.createElement('span');
        var query = window.matchMedia('(max-width: 767px)');
        var menuName = trigger.getAttribute('data-menu-name') || 'Menü';

        if (!slot || !backdrop) { return; }
        primaryMarker.className = 'spdRailPrimaryMarker';
        primaryMarker.hidden = true;
        if (primary && primary.parentNode) {
            primary.parentNode.insertBefore(primaryMarker, primary);
        }

        function usesDrawer() {
            return query.matches;
        }

        function placePrimary() {
            if (!primary) { return; }
            if (usesDrawer()) {
                slot.appendChild(primary);
            } else if (primaryMarker.parentNode) {
                primaryMarker.parentNode.insertBefore(primary, primaryMarker.nextSibling);
            }
        }

        function setOpen(open, returnFocus) {
            open = Boolean(open && usesDrawer());
            rail.classList.toggle('spdRailOpen', open);
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            trigger.setAttribute('aria-label', menuName + (open ? ' schließen' : ' öffnen'));
            backdrop.hidden = !open;
            document.body.classList.toggle('spdDrawerOpen', open);

            if (open) {
                var first = rail.querySelector(
                    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled])'
                );
                if (first) { first.focus(); }
            } else if (returnFocus) {
                trigger.focus();
            }
        }

        trigger.addEventListener('click', function () {
            setOpen(trigger.getAttribute('aria-expanded') !== 'true', false);
        });
        backdrop.addEventListener('click', function () { setOpen(false, true); });
        if (closeButton) {
            closeButton.addEventListener('click', function () { setOpen(false, true); });
        }
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                event.preventDefault();
                setOpen(false, true);
            }
        });

        function transition() {
            setOpen(false, false);
            placePrimary();
        }
        if (query.addEventListener) {
            query.addEventListener('change', transition);
        } else if (query.addListener) {
            query.addListener(transition);
        }
        window.addEventListener('orientationchange', function () { setOpen(false, false); });
        placePrimary();
    }

    rebuildFeedRows();
    externalLinksToNewTab(document);
    editActionsToHeader();
    setupCompactNavigation();
    wrapButtonLabels();

    // The user and voting admins render their buttons with Vue after this
    // script runs and replace them on every interaction, so a single pass
    // reaches only the server-rendered half.
    //
    // Coalesced into one task, so a burst of mutations costs one pass. The
    // pass is idempotent and converges: wrapping is itself a mutation, but the
    // guard means the follow-up pass changes nothing and so queues nothing.
    // A re-render by Vue drops the wrapper with the old button and the next
    // pass rebuilds it on the new one; the text node is moved rather than
    // replaced, so Vue's own handle on it stays valid either way.
    //
    // `setTimeout` and not `requestAnimationFrame`: a frame callback does not
    // run while the tab is not being painted, so in a background tab the whole
    // Vue-rendered half of the admin stayed unwrapped until it was focused.
    var queued = false;
    new MutationObserver(function () {
        if (queued) { return; }
        queued = true;
        setTimeout(function () {
            queued = false;
            rebuildFeedRows();
            externalLinksToNewTab(enhancementRoot);
            editActionsToHeader();
            wrapButtonLabels();
        }, 0);
    }).observe(enhancementRoot, { childList: true, subtree: true });
})();
</script>
JS;

        return $before . $script;
    }

    /**
     * The page footer. Rendered for every width, but hidden by the stylesheet
     * above the rail breakpoint, where the rail carries the same links under
     * Logout. Below that breakpoint the rail is not rendered at all, so this is
     * the only copy.
     */
    public function footerLine(string $before): string
    {
        return '<footer class="footer spdFooter" aria-label="' .
               Html::encode(\Yii::t('base', 'aria_footer')) . '"><div class="container">' .
               $this->legalLinksHtml(true) .
               '</div></footer>';
    }
}
