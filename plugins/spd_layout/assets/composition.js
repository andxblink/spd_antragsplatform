(function () {
    'use strict';

    var steps = [
        'Antrag ausfüllen',
        'Prüfen und absenden'
    ];

    function setControlLabel(control, label) {
        Array.prototype.slice.call(control.childNodes).forEach(function (node) {
            if (node.nodeType === 3) {
                control.removeChild(node);
            }
        });

        var current = control.querySelector(':scope > .spdActionLabel');
        if (!current) {
            current = document.createElement('span');
            current.className = 'spdActionLabel';
            control.appendChild(current);
        }
        current.textContent = label;
    }

    function taskHeading(label, id) {
        var heading = document.createElement('h2');
        heading.className = 'green spdCompositionTaskHeading';
        heading.id = id;
        heading.textContent = label;
        return heading;
    }

    function guardSubmission(form) {
        function reset() {
            delete form.dataset.spdSubmitting;
            form.classList.remove('spdSubmitting');
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.removeAttribute('aria-disabled');
            });
        }

        form.addEventListener('submit', function (event) {
            if (form.dataset.spdSubmitting) {
                event.preventDefault();
                return;
            }

            form.dataset.spdSubmitting = '1';
            form.classList.add('spdSubmitting');
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.setAttribute('aria-disabled', 'true');
            });
            window.setTimeout(reset, 10000);
        });
        window.addEventListener('pageshow', reset);
    }

    function progress(currentStep) {
        var nav = document.createElement('nav');
        var list = document.createElement('ol');

        nav.className = 'spdCompositionProgress';
        nav.setAttribute('aria-label', 'Fortschritt beim Antragstellen');

        steps.forEach(function (label, index) {
            var item = document.createElement('li');
            var number = document.createElement('span');
            var text = document.createElement('span');

            number.className = 'spdCompositionStepNumber';
            number.setAttribute('aria-hidden', 'true');
            number.textContent = String(index + 1);
            text.textContent = label;

            if (index + 1 === currentStep) {
                item.setAttribute('aria-current', 'step');
            }
            item.appendChild(number);
            item.appendChild(text);
            list.appendChild(item);
        });

        nav.appendChild(list);
        return nav;
    }

    function enhanceComposition() {
        var form = document.querySelector('#motionEditForm.draftForm');
        if (!form || form.dataset.spdComposition) {
            return false;
        }

        var content = form.querySelector(':scope > .content:not(.saveCancelRow)');
        var people = form.querySelector(':scope > .supporterForm');
        var actions = form.querySelector(':scope > .saveCancelRow');
        if (!content || !people || !actions) {
            return false;
        }

        form.dataset.spdComposition = 'compose';
        form.classList.add('spdComposition');
        content.dataset.spdCompositionSection = 'content';
        people.dataset.spdCompositionSection = 'people';
        actions.dataset.spdCompositionSection = 'actions';

        var contentHeading = taskHeading('Antrag', 'spdCompositionContentHeading');
        form.insertBefore(contentHeading, content);
        content.setAttribute('aria-labelledby', contentHeading.id);

        var peopleHeading = people.querySelector(':scope > legend');
        if (peopleHeading) {
            peopleHeading.textContent = 'Wer stellt den Antrag?';
        }

        var primary = actions.querySelector('button[name="save"]');
        var cancel = actions.querySelector('#cancel');
        if (primary) {
            setControlLabel(primary, 'Weiter zur Prüfung');
        }
        if (cancel) {
            setControlLabel(cancel, 'Zurück zur Übersicht');
        }

        document.querySelectorAll(
            '#mainContent > .alert-danger:not(.consultationwideWarning)'
        ).forEach(function (alert) {
            alert.setAttribute('role', 'alert');
            content.insertBefore(alert, content.firstChild);
        });

        var createRoute = window.location.pathname + window.location.search;
        if (createRoute.indexOf('/create') !== -1) {
            var heading = document.querySelector('#mainContent > h1');
            if (heading) {
                heading.textContent = 'Antrag stellen';
                document.title = document.title.replace(/^.*?(?= – | - |$)/, 'Antrag stellen');
            }
        }

        guardSubmission(form);
        form.parentNode.insertBefore(progress(1), form);
        return true;
    }

    function enhanceConfirmation() {
        var form = document.querySelector('.createConfirmPage #motionConfirmForm');
        var heading = document.querySelector('.createConfirmPage #mainContent > h1');
        if (!form || !heading || form.dataset.spdComposition) {
            return false;
        }

        var originalHeading = heading.textContent.trim();
        var titleSeparator = originalHeading.indexOf(': ');
        var motionTitle = titleSeparator === -1
            ? originalHeading
            : originalHeading.slice(titleSeparator + 2);

        form.dataset.spdComposition = 'review';
        form.classList.add('spdCompositionReview');
        heading.textContent = 'Antrag prüfen';
        document.title = document.title.replace(/^.*?(?= – | - |$)/, 'Antrag prüfen');

        var reviewProgress = progress(2);
        heading.insertAdjacentElement('afterend', reviewProgress);

        var summary = document.createElement('p');
        summary.className = 'spdReviewTitle';
        summary.textContent = motionTitle;
        reviewProgress.insertAdjacentElement('afterend', summary);

        var hint = document.querySelector('.createConfirmPage #mainContent > .content .alert-info p');
        if (hint) {
            hint.textContent = 'Prüfe deinen Antrag und deine Angaben. Du kannst alles noch korrigieren.';
        }

        var webMode = document.querySelector('.motionCreateToolbar input[value="web"]');
        var pdfMode = document.querySelector('.motionCreateToolbar input[value="pdf"]');
        if (webMode && webMode.parentElement) {
            setControlLabel(webMode.parentElement, 'Antrag');
        }
        if (pdfMode && pdfMode.parentElement) {
            setControlLabel(pdfMode.parentElement, 'PDF-Vorschau');
        }

        var modify = form.querySelector('button[name="modify"]');
        if (modify) {
            setControlLabel(modify, 'Zurück zum Bearbeiten');
        }

        guardSubmission(form);
        return true;
    }

    function initialize() {
        enhanceComposition();
        enhanceConfirmation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
