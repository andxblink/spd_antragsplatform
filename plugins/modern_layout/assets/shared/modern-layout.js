/*
 * Modern layout: the DOM pass.
 *
 * Markup adjustments no layout hook can reach. Loaded by
 * LayoutHooks::endPage() at the position the inline script used to occupy,
 * so it runs before upstream's own scripts, exactly as before.
 *
 * Every transform is guarded so it is idempotent and does nothing when the
 * upstream markup it expects is absent. Nothing here assigns innerHTML; all
 * nodes are built with the DOM API.
 */
(function () {
    // Translated labels arrive as data attributes on this script's own tag,
    // rendered with Yii::t() by LayoutHooks::endPage(). Read them while
    // document.currentScript still points at it.
    var labels = (document.currentScript && document.currentScript.dataset) || {};
    var FEED_OPEN_LABEL = labels.feedOpen || '';
    var FEED_COPY_LABEL = labels.feedCopy || '';
    var SELECT_ROW_LABEL = labels.selectRow || '';
    var SELECT_ROW_UNTITLED_LABEL = labels.selectRowUntitled || '';

    // Recovery links carry a bearer token in `code`. The server still needs
    // the value in the rendered form, but the URL must not remain in history
    // or become a referrer after the page has loaded. Caddy redacts the same
    // query keys in access logs.
    if (/\/recovery\/?$/.test(window.location.pathname) && window.location.search) {
        var params = new URLSearchParams(window.location.search);
        params.delete('code');
        params.delete('recoveryCode');
        params.delete('email');
        var cleanQuery = params.toString();
        window.history.replaceState(
            null,
            document.title,
            window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash
        );
    }

    // Split the initiator fieldset into two cards. The motion form renders it
    // as one flat list of rows inside `.initiatorData`, with `.contactHead` as
    // a divider partway down, and has no hook or wrapper. Safe to reparent:
    // `InitiatorForm.js` addresses every row with jQuery `.find()`
    // (descendant), and no stylesheet uses a child combinator under
    // `.initiatorData`, so the organisation and resolution toggles keep working.
    document.querySelectorAll('.initiatorData').forEach(function (data) {
        if (data.dataset.modernSplit) { return; }
        var head = data.querySelector(':scope > .contactHead');
        if (!head) { return; }
        data.dataset.modernSplit = '1';

        var nodes = Array.prototype.slice.call(data.children);
        var split = nodes.indexOf(head);
        var first = document.createElement('div');
        var second = document.createElement('div');
        first.className = 'modernFormCard modernFormCardPrimary';
        second.className = 'modernFormCard';

        nodes.slice(0, split).forEach(function (n) { first.appendChild(n); });
        nodes.slice(split).forEach(function (n) { second.appendChild(n); });
        data.appendChild(first);
        data.appendChild(second);
    });

    // `admin/consultation` writes its edit action as `std [ändern]`, with the
    // brackets as bare text nodes either side of the link. The link is styled as
    // a pill, and a pill in square brackets reads as a mistake. No selector
    // reaches a text node, so the brackets are trimmed here.
    document.querySelectorAll('.urlPathHolder > .shower').forEach(function (shower) {
        if (shower.dataset.modernBrackets) { return; }
        var link = shower.querySelector('a');
        if (!link) { return; }
        shower.dataset.modernBrackets = '1';

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
    function wrapButtonLabels(root) {
        root.querySelectorAll('.btn-link').forEach(function (btn) {
            if (btn.dataset.modernLabel) { return; }
            if (!btn.querySelector('.glyphicon, .fontello, .caret')) { return; }
            if (!btn.textContent.trim()) { return; }
            btn.dataset.modernLabel = '1';

            Array.prototype.slice.call(btn.childNodes).forEach(function (node) {
                if (node.nodeType === 3) {
                    if (!node.textContent.trim()) { return; }
                    var span = document.createElement('span');
                    span.className = 'modernBtnLabel';
                    btn.insertBefore(span, node);
                    span.appendChild(node);
                    return;
                }

                // Text already inside an element of its own needs no wrapper,
                // only the marker. Icons and screen-reader text are skipped.
                if (node.nodeType === 1 &&
                    !ICONS.test(String(node.className)) &&
                    !node.classList.contains('sr-only')) {
                    node.classList.add('modernBtnLabel');
                }
            });
        });
    }

    // The dense administrator list renders one bare checkbox per motion or
    // amendment row. Keep the row's existing title as the accessible name so
    // selecting a legal record is distinguishable without changing its text.
    function labelMotionSelections() {
        document.querySelectorAll('.motionListForm input.selectbox').forEach(function (input) {
            if (input.getAttribute('aria-label')) { return; }
            var row = input.closest('tr');
            var title = row && (row.querySelector('.titleCol a') || row.querySelector('.prefixCol'));
            var label = title ? title.textContent.replace(/\s+/g, ' ').trim() : '';
            input.setAttribute('aria-label', label ?
                SELECT_ROW_LABEL.replace('%TITLE%', function () { return label; }) : SELECT_ROW_UNTITLED_LABEL);
        });
    }

    // Every feed row is one bare link to an address that belongs in a reader,
    // not in this tab. Rebuilt as a name plus the two things anyone does with a
    // feed URL. There is no hook for this view, so it happens here.
    function rebuildFeedRows() {
        var FEEDS = '.feedMotions, .feedAmendments, .feedComments, .feedAll';
        document.querySelectorAll(FEEDS).forEach(function (link) {
            var row = link.closest('.title');
            if (!row || row.dataset.modernFeedRow) { return; }
            row.dataset.modernFeedRow = '1';

            // The stylesheet keys the feeds list off `.feedAll`, which is on a
            // link this function is about to delete. Mark the list itself so
            // the rules keep matching afterwards.
            var list = link.closest('.motionListStd');
            if (list) { list.classList.add('modernFeedList'); }

            var url  = link.href;
            var icon = link.querySelector('.fontello');
            var name = link.textContent.trim();

            row.textContent = '';
            if (icon) { row.appendChild(icon); }

            var label = document.createElement('span');
            label.className = 'modernFeedName';
            label.textContent = name;
            row.appendChild(label);

            row.appendChild(feedAction('a', 'modernFeedOpen', 'glyphicon-new-window',
                FEED_OPEN_LABEL, function (el) {
                    el.href = url;
                    el.target = '_blank';
                    el.rel = 'noopener noreferrer';
                }));

            row.appendChild(feedAction('button', 'modernFeedCopy', 'glyphicon-duplicate',
                FEED_COPY_LABEL, function (el) {
                    el.type = 'button';
                    el.addEventListener('click', function () { copyUrl(url, el); });
                }));
        });
    }

    function feedAction(tag, cls, glyph, label, init) {
        var el = document.createElement(tag);
        el.className = 'modernFeedAction ' + cls;
        el.title = label;
        var icon = document.createElement('span');
        icon.className = 'glyphicon ' + glyph;
        icon.setAttribute('aria-hidden', 'true');
        el.appendChild(icon);
        var srLabel = document.createElement('span');
        srLabel.className = 'sr-only';
        srLabel.textContent = label;
        el.appendChild(srLabel);
        init(el);
        return el;
    }

    function copyUrl(url, button) {
        function done() {
            button.classList.add('modernCopied');
            setTimeout(function () { button.classList.remove('modernCopied'); }, 1400);
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
    function externalLinksToNewTab() {
        document.querySelectorAll('a[href]').forEach(function (a) {
            if (a.dataset.modernExternal) { return; }
            if (!/^https?:/i.test(a.getAttribute('href') || '')) { return; }
            if (a.hostname === location.hostname) { return; }

            a.dataset.modernExternal = '1';
            a.target = '_blank';
            a.rel = (a.rel ? a.rel + ' ' : '') + 'noopener noreferrer';
        });
    }

    // The edit action for a content block belongs on the card's header, beside
    // the title it edits, not floated inside the body above the first line of
    // the text. Upstream puts the Redeliste equivalent in its heading already;
    // this brings the content pages in line with it.
    function editActionsToHeader() {
        document.querySelectorAll('.editCaller').forEach(function (btn) {
            if (btn.dataset.modernInHeader) { return; }

            var body = btn.closest('.content');
            if (!body) { return; }

            // The heading is not always the immediate previous sibling: the
            // consultation start page has a hidden mobile nav between the two.
            var header = body.previousElementSibling;
            while (header && !/^H[1-3]$/.test(header.tagName)) {
                header = header.previousElementSibling;
            }
            if (!header) { return; }

            btn.dataset.modernInHeader = '1';
            header.appendChild(btn);
        });
    }

    rebuildFeedRows();
    externalLinksToNewTab();
    editActionsToHeader();
    wrapButtonLabels(document);
    labelMotionSelections();

    // The user and voting admins, and 4.18's debate admin which hosts a voting
    // admin of its own, render their buttons with Vue after this script runs
    // and replace them on every interaction, so a single pass reaches only the
    // server-rendered half. Observe only those declared component roots. Watching document.body made every unrelated DOM update
    // in a long page run all five transforms again and coupled this plugin to
    // upstream implementation details outside the components it owns.
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
    if (window.MutationObserver) {
        document.querySelectorAll('.userAdmin, .votingAdmin, .currentDebateAdmin').forEach(function (root) {
            var queued = false;
            new MutationObserver(function () {
                if (queued) { return; }
                queued = true;
                setTimeout(function () {
                    queued = false;
                    wrapButtonLabels(root);
                }, 0);
            }).observe(root, { childList: true, subtree: true });
        });
    }
})();
