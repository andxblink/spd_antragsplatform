(function () {
    'use strict';

    var submittedKey = 'spdMotionJustSubmitted';

    function consumeSubmittedMarker() {
        try {
            if (window.sessionStorage.getItem(submittedKey) !== '1') {
                return false;
            }
            window.sessionStorage.removeItem(submittedKey);
            return true;
        } catch (error) {
            return false;
        }
    }

    function markSubmitted() {
        try {
            window.sessionStorage.setItem(submittedKey, '1');
        } catch (error) {
            // Storage can be disabled. The canonical status route still works.
        }
    }

    function moveDetailSummary() {
        var summary = document.querySelector('.spdMotionStatusSummary');
        var heading = document.querySelector('#mainContent > .primaryHeader');
        if (!summary || !heading) {
            return false;
        }

        heading.insertAdjacentElement('afterend', summary);

        if (consumeSubmittedMarker()) {
            var success = document.createElement('p');
            success.className = 'spdStatusSuccess';
            success.setAttribute('role', 'status');
            success.textContent = 'Dein Antrag wurde erfolgreich übermittelt.';
            summary.insertBefore(success, summary.firstChild);
        }
        return true;
    }

    function canonicalMotionUrl() {
        if (!/\/createdone\/?$/.test(window.location.pathname)) {
            return null;
        }
        return window.location.pathname.replace(/\/createdone\/?$/, '') +
            window.location.hash;
    }

    function routeSubmissionResult() {
        var resultForm = document.querySelector('#motionConfirmedForm');
        var canonical = canonicalMotionUrl();
        if (!resultForm || !canonical) {
            return false;
        }

        if (document.querySelector('.promoUrl')) {
            var row = resultForm.querySelector('.btnRow');
            if (row && !row.querySelector('.spdStatusResultLink')) {
                var link = document.createElement('a');
                link.className = 'btn btn-primary spdStatusResultLink';
                link.href = canonical;
                link.textContent = 'Antrag und Status ansehen';
                row.insertBefore(link, row.firstChild);
            }
            return true;
        }

        markSubmitted();
        window.location.replace(canonical);
        return true;
    }

    function enhanceMyMotions() {
        // The own-Anträge list is server-rendered by Antragsgrün. Do not infer
        // legal status from translated text in the browser. The upstream label
        // remains untouched; detail and consultation rows use the model's
        // authoritative status directly.
    }

    function nameMotionPagination() {
        document.querySelectorAll('nav.motionPrevNextLinks').forEach(function (nav) {
            var position = nav.classList.contains('toolbarAtBottom') ? 'unten' : 'oben';
            nav.setAttribute('aria-label', 'Navigation zwischen Anträgen ' + position);
        });
    }

    function initialize() {
        if (routeSubmissionResult()) {
            return;
        }
        moveDetailSummary();
        enhanceMyMotions();
        nameMotionPagination();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
