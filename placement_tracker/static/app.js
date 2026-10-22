/* ==========================================================================
   Placement Application Tracker - app.js

   Small, dependency free behaviour for the shell:
     - the sidebar drawer on a narrow screen
     - the profile and notification menus
     - the confirmation dialog for destructive actions
     - the search box that filters the table while you type

   The search is done by the server. Only the rows of the current page are
   ever sent to the browser.
   ========================================================================== */

(function () {
    'use strict';

    /* ----------------------------------------------------------------------
       The sidebar drawer
    ---------------------------------------------------------------------- */
    var shell = document.getElementById('appShell');

    document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (shell) { shell.classList.toggle('nav-open'); }
        });
    });

    document.querySelectorAll('[data-nav-close]').forEach(function (element) {
        element.addEventListener('click', function () {
            if (shell) { shell.classList.remove('nav-open'); }
        });
    });

    /* ----------------------------------------------------------------------
       Dropdown menus
    ---------------------------------------------------------------------- */
    function closeMenus() {
        document.querySelectorAll('.profile-menu.open').forEach(function (menu) {
            menu.classList.remove('open');
        });
        document.querySelectorAll('[data-menu-toggle][aria-expanded]').forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    document.querySelectorAll('[data-menu-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var menu = toggle.closest('.profile-menu');
            if (!menu) { return; }
            var wasOpen = menu.classList.contains('open');
            closeMenus();
            if (!wasOpen) {
                menu.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', closeMenus);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    /* ----------------------------------------------------------------------
       The confirmation dialog
    ---------------------------------------------------------------------- */
    var dialog = document.getElementById('confirmDialog');
    var pendingForm = null;

    function bindConfirmForms() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === '1') { return; }
                event.preventDefault();

                if (!dialog || typeof dialog.showModal !== 'function') {
                    // Older browser: fall back to the native prompt.
                    if (window.confirm(form.dataset.confirm)) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                    return;
                }

                pendingForm = form;
                var message = document.getElementById('confirmMessage');
                if (message) { message.textContent = form.dataset.confirm; }
                if (!dialog.open) { dialog.showModal(); }
            });
        });
    }

    if (dialog) {
        var accept = dialog.querySelector('[data-confirm-accept]');
        var cancel = dialog.querySelector('[data-confirm-cancel]');

        if (accept) {
            accept.addEventListener('click', function () {
                dialog.close();
                if (pendingForm) {
                    pendingForm.dataset.confirmed = '1';
                    pendingForm.submit();
                    pendingForm = null;
                }
            });
        }

        if (cancel) {
            cancel.addEventListener('click', function () {
                dialog.close();
                pendingForm = null;
            });
        }

        dialog.addEventListener('close', function () {
            pendingForm = null;
        });

        // Clicking the backdrop closes the dialog.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) { dialog.close(); }
        });
    }

    bindConfirmForms();

    /* ----------------------------------------------------------------------
       Search while you type
    ---------------------------------------------------------------------- */
    var results = document.querySelector('[data-results]');
    var searchInputs = Array.prototype.slice.call(document.querySelectorAll('[data-search-input]'));
    var filterForm = document.getElementById('filterForm');
    var timer = null;
    var lastTerm = null;
    var requestInFlight = null;

    function syncInputs(value) {
        searchInputs.forEach(function (input) {
            if (input.value !== value) { input.value = value; }
            var wrap = input.closest('.topbar-search') || input.closest('.filter-search');
            if (wrap) { wrap.classList.toggle('has-value', value !== ''); }
        });
    }

    function currentQuery() {
        var params = new URLSearchParams();

        if (filterForm) {
            new FormData(filterForm).forEach(function (value, key) {
                if (value !== '' && value !== null && typeof value === 'string') {
                    params.set(key, value);
                }
            });
        }

        var term = searchInputs.length ? searchInputs[0].value : '';
        if (term !== '') { params.set('q', term); }

        // The page always goes back to the first page when the search changes.
        params.delete('page');

        return params;
    }
    function setLoading(on) {
        var bar = document.querySelector('[data-results-loading]');
        if (bar) { bar.classList.toggle('on', on); }
        if (results) { results.classList.toggle('is-loading', on); }
    }

    function render(payload) {
        var table = document.querySelector('[data-results-table]');
        var pagination = document.querySelector('[data-results-pagination]');
        var summary = document.querySelector('[data-results-summary]');
        var heading = document.querySelector('[data-results-count]');

        if (table && payload.table_html !== undefined) { table.innerHTML = payload.table_html; }
        if (pagination && payload.pagination_html !== undefined) { pagination.innerHTML = payload.pagination_html; }
        if (summary && payload.summary_html !== undefined) { summary.innerHTML = payload.summary_html; }
        if (heading && payload.total !== undefined) {
            heading.textContent = payload.total + (payload.total === 1 ? ' application' : ' applications');
        }
    }

    function runSearch() {
        if (!results) { return; }   // no table on this page, the form submits normally

        var params = currentQuery();
        var term = params.get('q') || '';
        var url = '/api/applications.php?' + params.toString();
        if (term === lastTerm && requestInFlight === null) { return; }
        lastTerm = term;

        // A newer request makes the older one pointless.
        if (requestInFlight) { requestInFlight.abort(); }

        var controller = new AbortController();
        requestInFlight = controller;
        setLoading(true);

        fetch(url, {
            headers: { 'X-Requested-With': 'fetch' },
            signal: controller.signal,
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) { throw new Error('Search failed: ' + response.status); }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || payload.ok !== true) { return; }
                render(payload);
                window.history.replaceState({}, '', url);
            })
            .catch(function (error) {
                if (error.name === 'AbortError') { return; }
                console.error(error);
            })
            .finally(function () {
                if (requestInFlight === controller) {
                    requestInFlight = null;
                    setLoading(false);
                }
            });
    }

    function debounce(fn, wait) {
        var handle = null;
        return function () {
            var args = arguments;
            var self = this;
            window.clearTimeout(handle);
            handle = window.setTimeout(function () { fn.apply(self, args); }, wait);
        };
    }

    var debouncedSearch = debounce(runSearch, 300);

    searchInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            syncInputs(input.value);
            debouncedSearch();
        });

        // Enter searches straight away instead of waiting for the debounce.
        input.form && input.form.addEventListener('submit', function (event) {
            if (!results) { return; }  // no table: let the browser navigate
            event.preventDefault();
            syncInputs(input.value);
            runSearch();
        });
    });

    // The clear button empties the search and refreshes the table.
    document.querySelectorAll('[data-search-clear]').forEach(function (button) {
        button.addEventListener('click', function () {
            syncInputs('');
            lastTerm = null;
            if (results) {
                runSearch();
            } else {
                window.location.href = '/applications.php';
            }
            var first = searchInputs[0];
            if (first) { first.focus(); }
        });
    });

    // Changing a filter re-runs the search straight away.
    if (filterForm) {
        filterForm.addEventListener('change', function (event) {
            if (event.target.matches('select, input[type="date"]')) {
                lastTerm = null;
                runSearch();
            }
        });
    }

    // The page size selector keeps the current search and filters.
    document.querySelectorAll('[data-per-page]').forEach(function (select) {
        select.addEventListener('change', function () {
            var params = new URLSearchParams(window.location.search);
            params.set('per_page', select.value);
            params.delete('page');
            var query = params.toString();
            window.location.href = '/applications.php' + (query ? '?' + query : '');
        });
    });

    // Keep the URL tidy when the page is loaded with a search already set.
    syncInputs(searchInputs.length ? searchInputs[0].value : '');
})();
