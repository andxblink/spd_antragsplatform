(function () {
    'use strict';

    function hasActiveMotionFilter(form) {
        return Array.prototype.some.call(form.elements, function (control) {
            if (!control.name ||
                control.name.indexOf('Search[') !== 0 ||
                control.name === 'Search[sort]' ||
                control.disabled) {
                return false;
            }
            if (control.type === 'checkbox' || control.type === 'radio') {
                return control.checked;
            }
            return String(control.value || '').trim() !== '';
        });
    }

    function enhanceMotionTable() {
        var table = document.querySelector('.adminMotionTable');
        if (!table || table.dataset.spdSurface) {
            return false;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'spdTableScroll';
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'Antragsliste');
        wrapper.setAttribute('tabindex', '0');
        var grips = table.previousElementSibling &&
            table.previousElementSibling.classList.contains('JCLRgrips')
            ? table.previousElementSibling
            : null;
        table.parentNode.insertBefore(wrapper, grips || table);
        if (grips) {
            wrapper.appendChild(grips);
        }
        wrapper.appendChild(table);

        table.querySelectorAll('tbody tr').forEach(function (row) {
            var checkbox = row.querySelector('input.selectbox');
            if (!checkbox) {
                return;
            }
            var kind = checkbox.name === 'amendments[]' ? 'Änderungsantrag' : 'Antrag';
            var prefix = row.querySelector('.prefixCol')?.textContent.trim() || '';
            var title = row.querySelector('.titleCol')?.textContent.trim() || '';
            checkbox.setAttribute(
                'aria-label',
                [kind, prefix, title, 'auswählen'].filter(Boolean).join(' ')
            );
        });

        if (!table.querySelector('tbody > tr')) {
            var columnCount = Math.max(
                1,
                table.querySelectorAll('thead > tr:first-child > th').length
            );
            var body = table.tBodies[0] || table.createTBody();
            var row = body.insertRow();
            var cell = row.insertCell();
            var filter = document.querySelector('.motionListSearchForm');
            row.className = 'spdEmptyState';
            cell.colSpan = columnCount;
            cell.setAttribute('role', 'status');
            cell.textContent = filter && hasActiveMotionFilter(filter)
                ? 'Keine Anträge passen zu den gewählten Filtern.'
                : 'Noch keine Anträge vorhanden.';
        }

        table.dataset.spdSurface = '1';
        return true;
    }

    function enhanceUserAccounts() {
        var list = document.querySelector('.userAdminList > section > .userList');
        if (!list) {
            return false;
        }
        var section = list.parentElement;
        if (section.dataset.spdAccounts) {
            return true;
        }

        var heading = document.createElement('h2');
        heading.id = 'spdUserAccountsTitle';
        heading.className = 'green';
        heading.textContent = 'Benutzer*innen';
        section.insertBefore(heading, section.firstChild);
        section.classList.add('spdUserAccountsCard');
        section.setAttribute('aria-labelledby', heading.id);
        section.removeAttribute('aria-label');
        section.querySelectorAll('.groupFilter select, .groupFilter .selectize-input > input')
            .forEach(function (control) {
                control.setAttribute('aria-label', 'Benutzer*innen nach Gruppe filtern');
            });
        section.dataset.spdAccounts = '1';
        return true;
    }

    function enhanceUserCreationAccessibility() {
        var form = document.querySelector('#accountsCreateForm');
        var select = form && form.querySelector('.adminTypeSelect');
        if (!select) {
            return false;
        }

        var label = 'Art des Benutzerkontos';
        select.setAttribute('aria-label', label);
        var selectizeInput = form.querySelector('.adminType .selectize-input > input');
        if (selectizeInput) {
            selectizeInput.setAttribute('aria-label', label);
        }
        return Boolean(selectizeInput);
    }

    function authorizedLink(selector) {
        var link = document.querySelector(selector);
        if (!link || !link.href) {
            return null;
        }
        return link;
    }

    function adminSettingIcon(link) {
        var path = new URL(link.href, window.location.href).pathname;
        var iconBySuffix = [
            ['/admin/motion-type/typecreate', 'file-plus-corner'],
            ['/admin/motion-type/type', 'file-cog'],
            ['/admin/consultation', 'calendar-cog'],
            ['/admin/appearance', 'panels-top-left'],
            ['/admin/translation', 'languages'],
            ['/admin/agenda', 'list-tree'],
            ['/admin-votings', 'vote'],
            ['/admin-speech', 'mic-vocal'],
            ['/admin/users', 'users-round'],
            ['/admin/siteconsultations', 'calendar-plus-2'],
            ['/admin/list', 'clipboard-pen-line'],
            ['/page', 'newspaper'],
        ];
        var match = iconBySuffix.find(function (entry) {
            return path.endsWith(entry[0]);
        });
        return match ? match[1] : null;
    }

    function decorateAdminSettingLinks(list) {
        list.querySelectorAll('a').forEach(function (link) {
            if (link.querySelector(':scope > .spdAdminSettingsIcon')) {
                return;
            }
            var iconName = adminSettingIcon(link);
            if (!iconName) {
                return;
            }
            var icon = document.createElement('span');
            icon.className = 'spdAdminSettingsIcon spdAdminSettingsIcon-' + iconName;
            icon.setAttribute('aria-hidden', 'true');
            link.insertBefore(icon, link.firstChild);
        });
    }

    function enhanceAdminIndex() {
        var list = document.querySelector('.adminIndex .adminMenuList');
        if (!list || list.dataset.spdSettings) {
            return false;
        }

        var motionListLink = authorizedLink('.spdRail #motionListLink');
        var listAlreadyContainsMotionLink = motionListLink && Array.from(
            list.querySelectorAll('a')
        ).some(function (link) {
            return link.href === motionListLink.href;
        });
        if (motionListLink && !listAlreadyContainsMotionLink) {
            var motionListItem = document.createElement('li');
            var motionListTask = document.createElement('a');
            motionListItem.className = 'spdMotionListTask';
            motionListTask.href = motionListLink.href;
            motionListTask.textContent = 'Anträge bearbeiten';
            motionListItem.appendChild(motionListTask);
            list.insertBefore(motionListItem, list.firstChild);
        }

        var motionTypeGroup = Array.from(list.children).find(function (item) {
            return item.querySelector(':scope > ul') && !item.querySelector(':scope > a');
        });
        var firstAdditionalSetting = motionTypeGroup && motionTypeGroup.nextElementSibling;
        if (firstAdditionalSetting) {
            var additionalSettingsLabel = document.createElement('li');
            additionalSettingsLabel.className = 'spdAdminAdditionalSettingsLabel';
            additionalSettingsLabel.textContent = 'Weitere Einstellungen';
            list.insertBefore(additionalSettingsLabel, firstAdditionalSetting);
        }

        var settingsHeading = document.createElement('h2');
        settingsHeading.className = 'spdAdminSettingsTitle';
        settingsHeading.textContent = 'Allgemeine Einstellungen';
        list.parentNode.insertBefore(settingsHeading, list);
        decorateAdminSettingLinks(list);
        list.dataset.spdSettings = '1';
        return true;
    }

    function enhanceSettingsAccessibility() {
        document.querySelectorAll('.glyphicon-info-sign[aria-label]').forEach(function (icon) {
            icon.setAttribute('role', 'img');
            icon.removeAttribute('tabindex');
        });
        var amendmentNumbering = document.querySelector('#amendmentNumbering');
        if (amendmentNumbering) {
            amendmentNumbering.setAttribute(
                'aria-label',
                'Nummerierung von Änderungsanträgen'
            );
        }
    }

    function enhanceMainLandmark() {
        var main = document.querySelector('#mainContent');
        if (main && !main.hasAttribute('tabindex')) {
            main.setAttribute('tabindex', '-1');
        }
    }

    function initialize() {
        enhanceMainLandmark();
        enhanceMotionTable();
        enhanceAdminIndex();
        enhanceSettingsAccessibility();
        enhanceUserCreationAccessibility();
        var creationForm = document.querySelector('#accountsCreateForm');
        if (creationForm) {
            var creationObserver = new MutationObserver(function () {
                enhanceUserCreationAccessibility();
            });
            creationObserver.observe(creationForm, { childList: true, subtree: true });
        }
        if (enhanceUserAccounts()) {
            return;
        }

        var root = document.querySelector('.userAdmin');
        if (!root) {
            return;
        }
        var observer = new MutationObserver(function () {
            if (enhanceUserAccounts()) {
                observer.disconnect();
            }
        });
        observer.observe(root, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
