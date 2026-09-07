/**
 * Relation Select – Split-Auswahl (verfuegbar | ausgewaehlt) mit Suche,
 * Sortierung per Drag & Drop und optionalem Modal.
 *
 * Quellen:
 *  - <input data-relation-config='{...}'>  Datensaetze per API (rex-api-call=relation_select)
 *  - <select data-relation-select>          Optionen des Selects (z.B. YForm be_manager_relation),
 *                                           keine API, keine Tabellenfreigabe noetig
 *
 * Keine Abhaengigkeiten: laeuft im Backend und im Frontend (Icons als Inline-SVG).
 */
(function () {
    'use strict';

    var FALLBACK_I18N = {
        de: {
            search_placeholder: 'Suchen …', available_items: 'Verfügbar', selected_items: 'Ausgewählt',
            add: 'Hinzufügen', add_all: 'Alle sichtbaren hinzufügen', remove: 'Entfernen', clear_all: 'Auswahl leeren',
            sort: 'Sortieren', choose: 'Auswählen', modal_title: 'Einträge auswählen', cancel: 'Abbrechen',
            apply: 'Übernehmen', no_results: 'Keine Einträge', empty_selection: 'Noch nichts ausgewählt',
            error_loading: 'Fehler beim Laden der Daten', online: 'Online', offline: 'Offline'
        },
        en: {
            search_placeholder: 'Search …', available_items: 'Available', selected_items: 'Selected',
            add: 'Add', add_all: 'Add all visible', remove: 'Remove', clear_all: 'Clear selection',
            sort: 'Sort', choose: 'Choose', modal_title: 'Select entries', cancel: 'Cancel',
            apply: 'Apply', no_results: 'No entries', empty_selection: 'Nothing selected yet',
            error_loading: 'Error loading data', online: 'Online', offline: 'Offline'
        }
    };

    // Eigene Symbole (Inline-SVG), damit das Widget auch ohne Backend-Icon-Font laeuft
    var ICONS = {
        search: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.8l-.3-.3A6.5 6.5 0 1 0 14 15.5l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/></svg>',
        plus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>',
        minus: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13H5v-2h14z"/></svg>',
        check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>',
        addAll: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h12v2H3zm0 6h12v2H3zm0 6h8v2H3zm16-4v-3h-2v3h-3v2h3v3h2v-3h3v-2z"/></svg>',
        trash: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM8 9h8v10H8V9zm7.5-5-1-1h-5l-1 1H5v2h14V4z"/></svg>',
        grip: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>',
        close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4l5.6 5.6L5 17.6 6.4 19l5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6z"/></svg>',
        list: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h2v-2H3zm0 4h2v-2H3zm0-8h2V7H3zm4 4h14v-2H7zm0 4h14v-2H7zM7 7v2h14V7z"/></svg>',
        link: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.9 12a3.1 3.1 0 0 1 3.1-3.1h4V7H7a5 5 0 0 0 0 10h4v-1.9H7A3.1 3.1 0 0 1 3.9 12zM8 13h8v-2H8zm9-6h-4v1.9h4a3.1 3.1 0 0 1 0 6.2h-4V17h4a5 5 0 0 0 0-10z"/></svg>'
    };

    function icon(name) { return '<span class="rs-icon rs-icon-' + name + '">' + ICONS[name] + '</span>'; }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Farbwerte nur in sicheren Formen (kein CSS-Injection ueber style="")
    function safeColor(value) {
        value = String(value || '').trim();
        return /^(#[0-9a-f]{3,8}|[a-z]{3,20}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%]+\))$/i.test(value) ? value : '';
    }

    function settings() {
        return (typeof rex !== 'undefined' && rex.relation_select) ? rex.relation_select : {};
    }

    function i18n() {
        var base = settings().i18n;
        if (base) return base;
        var lang = (document.documentElement.lang || 'de').slice(0, 2).toLowerCase();
        return FALLBACK_I18N[lang] || FALLBACK_I18N.de;
    }

    function t(key) {
        var strings = i18n();
        return strings[key] || FALLBACK_I18N.de[key] || key;
    }

    function fire(el, name, detail) {
        el.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: detail || {} }));
        // rex:change wie bei anderen Widgets mit dem Feld als Container (Handler erwarten ein jQuery-Objekt)
        if (window.jQuery && name === 'change') window.jQuery(el).trigger('rex:change', [window.jQuery(el)]);
    }

    /**
     * Label-HTML nach displayFormat: "color:feld|badge:feld|(id)" -- Farbpunkt,
     * Badge (status als Online/Offline-Punkt), ID, danach der Label-Text.
     */
    function renderLabel(item, format) {
        var html = '';
        String(format || '').split('|').forEach(function (part) {
            part = part.trim();
            if (part.indexOf('color:') === 0) {
                var color = safeColor(item[part.substring(6).trim()]);
                html += color
                    ? '<span class="rs-color" style="background-color:' + esc(color) + '"></span>'
                    : '<span class="rs-color rs-color-empty"></span>';
            } else if (part.indexOf('badge:') === 0) {
                var field = part.substring(6).trim();
                var raw = item[field];
                if (field === 'status') {
                    var online = String(raw) === '1';
                    html += '<span class="rs-state ' + (online ? 'rs-state-online' : 'rs-state-offline') + '" title="' + esc(online ? t('online') : t('offline')) + '"></span>';
                } else if (raw !== null && raw !== undefined && String(raw).trim() !== '') {
                    html += '<span class="rs-badge">' + esc(String(raw).trim()) + '</span>';
                }
            } else if (part === '(id)') {
                html += '<span class="rs-id">' + esc(item.value) + '</span>';
            }
        });
        return html + '<span class="rs-label">' + esc(item.label) + '</span>';
    }

    /* ------------------------------------------------------------------ */
    /* Widget                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @param {Object} opts
     *   multiple   {boolean}
     *   format     {string}     displayFormat
     *   load       {function}   () => Promise<Array<{value,label,...}>>
     *   selected   {function}   () => Array<string>   aktuelle Auswahl (Reihenfolge)
     *   commit     {function}   (values: Array<string>) => void
     */
    function Widget(opts) {
        this.opts = opts;
        this.items = new Map();       // value -> item
        this.selected = [];           // geordnete Werte
        this.root = this.build();
        this.available = this.root.querySelector('.rs-available-list');
        this.chosen = this.root.querySelector('.rs-selected-list');
        this.search = this.root.querySelector('.rs-search');
        this.bind();
    }

    Widget.prototype.build = function () {
        var el = document.createElement('div');
        el.className = 'rs-widget relation-select-widget' + (this.opts.multiple ? '' : ' rs-single');
        el.innerHTML =
            '<div class="rs-pane rs-pane-available">' +
                '<div class="rs-pane-head">' +
                    '<label class="rs-search-wrap">' + icon('search') + '<input type="text" class="rs-search" placeholder="' + esc(t('search_placeholder')) + '" autocomplete="off"></label>' +
                    (this.opts.multiple ? '<button type="button" class="rs-btn rs-btn-icon rs-add-all" title="' + esc(t('add_all')) + '">' + icon('addAll') + '</button>' : '') +
                '</div>' +
                '<ul class="rs-list rs-available-list" role="listbox" aria-label="' + esc(t('available_items')) + '"></ul>' +
            '</div>' +
            '<div class="rs-pane rs-pane-selected">' +
                '<div class="rs-pane-head">' +
                    '<span class="rs-pane-title">' + esc(t('selected_items')) + ' <span class="rs-count">0</span></span>' +
                    '<button type="button" class="rs-btn rs-btn-icon rs-clear-all" title="' + esc(t('clear_all')) + '">' + icon('trash') + '</button>' +
                '</div>' +
                '<ul class="rs-list rs-selected-list" role="listbox" aria-label="' + esc(t('selected_items')) + '"></ul>' +
            '</div>';
        return el;
    };

    Widget.prototype.load = function () {
        var self = this;
        self.available.innerHTML = '<li class="rs-empty rs-loading">…</li>';
        return self.opts.load().then(function (rows) {
            self.items.clear();
            (rows || []).forEach(function (row) {
                if (row && row.value !== null && row.value !== undefined) {
                    self.items.set(String(row.value), row);
                }
            });
            self.selected = self.opts.selected().filter(function (v) { return self.items.has(v); });
            self.render();
        }).catch(function (err) {
            self.available.innerHTML = '<li class="rs-empty rs-error">' + esc(t('error_loading')) + '</li>';
            if (window.console) console.error('relation_select:', err);
        });
    };

    Widget.prototype.itemHtml = function (item, chosen) {
        return '<li class="rs-item" data-value="' + esc(item.value) + '" tabindex="0" role="option" draggable="' + (chosen && this.opts.multiple ? 'true' : 'false') + '">' +
            (chosen && this.opts.multiple ? '<span class="rs-handle" title="' + esc(t('sort')) + '">' + icon('grip') + '</span>' : '') +
            '<span class="rs-item-body">' + renderLabel(item, this.opts.format) + '</span>' +
            '<button type="button" class="rs-item-action ' + (chosen ? 'rs-remove' : 'rs-add') + '" title="' + esc(chosen ? t('remove') : t('add')) + '" tabindex="-1">' + icon(chosen ? 'minus' : 'plus') + '</button>' +
        '</li>';
    };

    Widget.prototype.render = function () {
        var self = this;
        var chosenSet = {};
        self.selected.forEach(function (v) { chosenSet[v] = true; });

        var availableHtml = '';
        self.items.forEach(function (item, value) {
            if (!chosenSet[value]) availableHtml += self.itemHtml(item, false);
        });
        self.available.innerHTML = availableHtml + '<li class="rs-empty" hidden>' + esc(t('no_results')) + '</li>';

        var chosenHtml = '';
        self.selected.forEach(function (value) { chosenHtml += self.itemHtml(self.items.get(value), true); });
        self.chosen.innerHTML = chosenHtml || '<li class="rs-empty">' + esc(t('empty_selection')) + '</li>';

        self.root.querySelector('.rs-count').textContent = String(self.selected.length);
        self.applySearch();
    };

    Widget.prototype.applySearch = function () {
        var query = (this.search.value || '').trim().toLowerCase();
        var items = this.available.querySelectorAll('.rs-item');
        var visible = 0;
        Array.prototype.forEach.call(items, function (li) {
            var hit = query === '' || li.textContent.toLowerCase().indexOf(query) !== -1;
            li.hidden = !hit;
            if (hit) visible++;
        });
        var empty = this.available.querySelector('.rs-empty');
        if (empty) empty.hidden = items.length > 0 && visible > 0;
    };

    Widget.prototype.add = function (value) {
        if (!this.items.has(value) || this.selected.indexOf(value) !== -1) return;
        if (this.opts.multiple) this.selected.push(value);
        else this.selected = [value];
        this.render();
        this.opts.commit(this.selected.slice());
    };

    Widget.prototype.remove = function (value) {
        var index = this.selected.indexOf(value);
        if (index === -1) return;
        this.selected.splice(index, 1);
        this.render();
        this.opts.commit(this.selected.slice());
    };

    Widget.prototype.setSelected = function (values) {
        var self = this;
        self.selected = values.filter(function (v) { return self.items.has(v); });
        self.render();
    };

    Widget.prototype.bind = function () {
        var self = this;

        self.search.addEventListener('input', function () { self.applySearch(); });
        self.search.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = self.available.querySelector('.rs-item:not([hidden])');
                if (first) self.add(first.getAttribute('data-value'));
            }
        });

        var addAll = self.root.querySelector('.rs-add-all');
        if (addAll) {
            addAll.addEventListener('click', function () {
                Array.prototype.forEach.call(self.available.querySelectorAll('.rs-item:not([hidden])'), function (li) {
                    var value = li.getAttribute('data-value');
                    if (self.selected.indexOf(value) === -1) self.selected.push(value);
                });
                self.render();
                self.opts.commit(self.selected.slice());
            });
        }
        self.root.querySelector('.rs-clear-all').addEventListener('click', function () {
            if (!self.selected.length) return;
            self.selected = [];
            self.render();
            self.opts.commit([]);
        });

        function itemOf(target) {
            var li = target.closest ? target.closest('.rs-item') : null;
            return li && li.getAttribute('data-value');
        }
        self.available.addEventListener('click', function (e) {
            var value = itemOf(e.target);
            if (value !== null && value !== undefined) self.add(value);
        });
        self.chosen.addEventListener('click', function (e) {
            if (e.target.closest('.rs-handle')) return;
            var value = itemOf(e.target);
            if (value !== null && value !== undefined) self.remove(value);
        });
        self.root.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var li = e.target.closest && e.target.closest('.rs-item');
            if (!li) return;
            e.preventDefault();
            var value = li.getAttribute('data-value');
            if (li.parentNode === self.available) self.add(value); else self.remove(value);
        });

        // Sortierung per nativem Drag & Drop
        var dragging = null;
        self.chosen.addEventListener('dragstart', function (e) {
            dragging = e.target.closest('.rs-item');
            if (!dragging) return;
            dragging.classList.add('rs-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', dragging.getAttribute('data-value')); } catch (err) { /* IE */ }
        });
        self.chosen.addEventListener('dragover', function (e) {
            if (!dragging) return;
            e.preventDefault();
            var over = e.target.closest('.rs-item');
            if (!over || over === dragging) return;
            var rect = over.getBoundingClientRect();
            var before = (e.clientY - rect.top) < rect.height / 2;
            self.chosen.insertBefore(dragging, before ? over : over.nextSibling);
        });
        self.chosen.addEventListener('drop', function (e) { e.preventDefault(); });
        self.chosen.addEventListener('dragend', function () {
            if (!dragging) return;
            dragging.classList.remove('rs-dragging');
            dragging = null;
            var order = Array.prototype.map.call(self.chosen.querySelectorAll('.rs-item'), function (li) { return li.getAttribute('data-value'); });
            if (order.join(',') !== self.selected.join(',')) {
                self.selected = order;
                self.root.querySelector('.rs-count').textContent = String(order.length);
                self.opts.commit(order.slice());
            }
        });
    };

    /* ------------------------------------------------------------------ */
    /* Modal                                                               */
    /* ------------------------------------------------------------------ */

    var scrollLock = null;
    function lockScroll() {
        if (scrollLock !== null) return;
        scrollLock = window.scrollY || document.documentElement.scrollTop || 0;
        document.documentElement.classList.add('rs-modal-open');
        document.documentElement.style.top = '-' + scrollLock + 'px';
    }
    function unlockScroll() {
        if (scrollLock === null) return;
        document.documentElement.classList.remove('rs-modal-open');
        document.documentElement.style.top = '';
        window.scrollTo(0, scrollLock);
        scrollLock = null;
    }

    function Modal(widget, title) {
        var self = this;
        self.widget = widget;
        self.snapshot = [];
        self.el = document.createElement('div');
        self.el.className = 'rs-modal';
        self.el.setAttribute('role', 'dialog');
        self.el.setAttribute('aria-modal', 'true');
        self.el.innerHTML =
            '<div class="rs-modal-backdrop"></div>' +
            '<div class="rs-modal-dialog">' +
                '<div class="rs-modal-header">' + icon('link') + '<span class="rs-modal-title">' + esc(title || t('modal_title')) + '</span>' +
                    '<button type="button" class="rs-modal-close" title="' + esc(t('cancel')) + '">' + icon('close') + '</button></div>' +
                '<div class="rs-modal-body"></div>' +
                '<div class="rs-modal-footer"><span class="rs-modal-status"></span>' +
                    '<button type="button" class="rs-btn rs-modal-cancel">' + esc(t('cancel')) + '</button>' +
                    '<button type="button" class="rs-btn rs-btn-primary rs-modal-apply">' + icon('check') + esc(t('apply')) + '</button></div>' +
            '</div>';
        self.el.querySelector('.rs-modal-body').appendChild(widget.root);
        document.body.appendChild(self.el);

        self.el.querySelector('.rs-modal-backdrop').addEventListener('click', function () { self.cancel(); });
        self.el.querySelector('.rs-modal-close').addEventListener('click', function () { self.cancel(); });
        self.el.querySelector('.rs-modal-cancel').addEventListener('click', function () { self.cancel(); });
        self.el.querySelector('.rs-modal-apply').addEventListener('click', function () { self.apply(); });
        self.onKey = function (e) {
            if (e.key === 'Escape' && self.el.classList.contains('rs-open')) { e.preventDefault(); self.cancel(); }
        };
    }

    Modal.prototype.open = function () {
        this.snapshot = this.widget.selected.slice();
        this.el.classList.add('rs-open');
        lockScroll();
        document.addEventListener('keydown', this.onKey);
        var search = this.widget.search;
        setTimeout(function () { search.focus(); }, 30);
    };

    Modal.prototype.close = function () {
        this.el.classList.remove('rs-open');
        document.removeEventListener('keydown', this.onKey);
        unlockScroll();
    };

    Modal.prototype.cancel = function () {
        this.widget.setSelected(this.snapshot);
        this.widget.opts.commit(this.snapshot.slice());
        this.close();
    };

    Modal.prototype.apply = function () {
        this.widget.opts.commit(this.widget.selected.slice());
        this.close();
        if (this.onApply) this.onApply();
    };

    /* ------------------------------------------------------------------ */
    /* Quellen                                                             */
    /* ------------------------------------------------------------------ */

    function apiUrl(config, selectedValues) {
        var params = new URLSearchParams({
            'rex-api-call': 'relation_select',
            table: config.table,
            value_field: config.valueField,
            label_field: config.labelField
        });
        if (config.displayFields) params.append('display_fields', config.displayFields);
        if (config.dbw) params.append('dbw', config.dbw);
        if (config.dbob) params.append('dbob', config.dbob);
        var clang = config.clang || (typeof rex !== 'undefined' && rex.clang_id) || 0;
        if (clang) params.append('clang', clang);
        if (selectedValues.length) params.append('values', selectedValues.join(','));
        if (config.token) params.append('token', config.token);
        params.append('_t', String(Date.now()));
        return (config.endpoint || 'index.php') + '?' + params.toString();
    }

    function parseValues(raw) {
        return String(raw || '').split(',').map(function (v) { return v.trim(); }).filter(function (v) { return v !== ''; });
    }

    // Quelle 1: Input mit data-relation-config (API)
    function initInput(input) {
        var config;
        try {
            config = JSON.parse(input.dataset.relationConfig || '{}');
        } catch (e) {
            if (window.console) console.error('relation_select: invalid data-relation-config', e);
            return;
        }
        if (!config.table || !config.valueField || !config.labelField) {
            if (window.console) console.error('relation_select: table, valueField and labelField are required');
            return;
        }
        var mode = input.dataset.relationMode || 'inline';
        var multiple = input.dataset.relationMultiple !== '0' && config.multiple !== false && mode !== 'inline-single';

        var widget = new Widget({
            multiple: multiple,
            format: config.displayFormat || config.displayFields || '',
            load: function () {
                return fetch(apiUrl(config, parseValues(input.value)), { cache: 'no-store', credentials: 'same-origin' }).then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                });
            },
            selected: function () { return parseValues(input.value); },
            commit: function (values) {
                var next = values.join(',');
                if (input.value === next) return;
                input.value = next;
                fire(input, 'change', { values: values });
            }
        });

        mount(input, widget, mode, input.dataset.relationTitle);
        widget.load();
    }

    // Quelle 2: <select> (be_manager_relation, eigene Selects) -- ohne API
    function initSelect(select) {
        var mode = select.dataset.relationSelect === 'modal' || select.dataset.relationMode === 'modal' ? 'modal' : 'inline';
        var multiple = select.multiple;

        var widget = new Widget({
            multiple: multiple,
            format: select.dataset.relationFormat || '',
            load: function () {
                var rows = [];
                Array.prototype.forEach.call(select.options, function (option) {
                    if (option.value === '') return;
                    var row = { value: option.value, label: option.textContent.trim() };
                    Object.keys(option.dataset).forEach(function (key) { row[key] = option.dataset[key]; });
                    rows.push(row);
                });
                return Promise.resolve(rows);
            },
            selected: function () {
                return Array.prototype.filter.call(select.options, function (o) { return o.selected && o.value !== ''; })
                    .map(function (o) { return o.value; });
            },
            commit: function (values) {
                var changed = false;
                Array.prototype.forEach.call(select.options, function (option) {
                    var shouldSelect = values.indexOf(option.value) !== -1 || (option.value === '' && !multiple && values.length === 0);
                    if (option.selected !== shouldSelect) { option.selected = shouldSelect; changed = true; }
                });
                // Reihenfolge der Auswahl = DOM-Reihenfolge der Optionen = Reihenfolge beim Absenden
                if (multiple) {
                    values.slice().reverse().forEach(function (value) {
                        var option = select.querySelector('option[value="' + CSS.escape(value) + '"]');
                        if (option && select.firstChild !== option) { select.insertBefore(option, select.firstChild); changed = true; }
                    });
                }
                if (changed) fire(select, 'change', { values: values });
            }
        });

        mount(select, widget, mode, select.dataset.relationTitle);
        widget.load();
    }

    function mount(field, widget, mode, title) {
        field.classList.add('rs-source');
        field.setAttribute('data-relation-initialized', '1');
        if (mode === 'modal') {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'rs-btn rs-open-modal';
            var updateButton = function () {
                var count = widget.selected.length;
                button.innerHTML = icon('list') + esc(title || t('choose')) + ' <span class="rs-count-badge' + (count ? ' rs-has-items' : '') + '">' + count + '</span>';
            };
            var modal = new Modal(widget, title);
            modal.onApply = updateButton;
            button.addEventListener('click', function () { modal.open(); });
            field.insertAdjacentElement('afterend', button);
            updateButton();
            var origCommit = widget.opts.commit;
            widget.opts.commit = function (values) { origCommit(values); updateButton(); };
            var origRender = widget.render;
            widget.render = function () { origRender.call(widget); updateButton(); };
        } else {
            field.insertAdjacentElement('afterend', widget.root);
        }
        field.rsWidget = widget;
    }

    /* ------------------------------------------------------------------ */
    /* Init                                                                */
    /* ------------------------------------------------------------------ */

    function init(container) {
        container = container && container.querySelectorAll ? container : document;
        Array.prototype.forEach.call(container.querySelectorAll('input[data-relation-config]:not([data-relation-initialized])'), initInput);

        var enhance = settings().enhance || 'attribute';
        var selector = 'select[data-relation-select]:not([data-relation-initialized])';
        Array.prototype.forEach.call(container.querySelectorAll(selector), initSelect);
        if (enhance === 'multiple' || enhance === 'all') {
            var auto = enhance === 'all'
                ? '[data-be-relation-wrapper] select:not([data-relation-initialized])'
                : '[data-be-relation-wrapper] select[multiple]:not([data-relation-initialized])';
            Array.prototype.forEach.call(container.querySelectorAll(auto), function (select) {
                if (select.dataset.relationSelect === 'off') return;
                initSelect(select);
            });
        }
    }

    window.RelationSelect = { init: init, Widget: Widget, renderLabel: renderLabel };

    if (window.jQuery) {
        window.jQuery(document).on('rex:ready', function (e, container) { init(container && container[0] ? container[0] : container); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); });
    else init(document);
})();
