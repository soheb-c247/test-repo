import './bootstrap';
import Alpine from 'alpinejs';
import schemaBuilder from './schema-builder';

window.Alpine = Alpine;

let uid = 0;
const nextId = () => ++uid;

/* ------------------------------------------------------------------ Toasts */
Alpine.store('toasts', {
    items: [],
    push(type, message, timeout = 5000) {
        const id = nextId();
        this.items.push({ id, type, message });
        if (timeout) setTimeout(() => this.remove(id), timeout);
    },
    remove(id) {
        this.items = this.items.filter((t) => t.id !== id);
    },
});

/* ------------------------------------------------------- Confirmation dialog
 * Any <form data-confirm="Message"> asks before submitting. Optional:
 * data-confirm-title, data-confirm-label, data-confirm-danger="1".
 */
Alpine.store('confirm', {
    open: false,
    title: '',
    message: '',
    label: 'Confirm',
    danger: false,
    form: null,
    ask(form) {
        this.form = form;
        this.title = form.dataset.confirmTitle || 'Are you sure?';
        this.message = form.dataset.confirm;
        this.label = form.dataset.confirmLabel || 'Confirm';
        this.danger = form.dataset.confirmDanger === '1';
        this.open = true;
    },
    accept() {
        const form = this.form;
        this.open = false;
        if (form) {
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    },
    cancel() {
        this.open = false;
        this.form = null;
    },
});

document.addEventListener(
    'submit',
    (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.dataset.confirm && form.dataset.confirmed !== '1') {
            event.preventDefault();
            event.stopImmediatePropagation();
            Alpine.store('confirm').ask(form);
            return;
        }

        // Prevent double submission; buttons are re-enabled on bfcache restore.
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }
        if (!event.defaultPrevented) {
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type=submit]').forEach((b) => b.setAttribute('aria-busy', 'true'));
        }
    },
    true,
);

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((f) => {
        delete f.dataset.submitting;
        delete f.dataset.confirmed;
    });
});

/* ---------------------------------------------------- "/" focuses search */
document.addEventListener('keydown', (event) => {
    if (event.key !== '/' || event.metaKey || event.ctrlKey) return;
    const tag = document.activeElement?.tagName;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable) return;
    const search = document.querySelector('[data-global-search]');
    if (search) {
        event.preventDefault();
        search.focus();
    }
});

/* ------------------------------------------------------- Patient picker
 * Debounced lookup by name, patient ID, phone or report number. Results
 * open in a dropdown (arrow keys + Enter supported); choosing one fills the
 * patient fields of the report form via the `patient-selected` event.
 */
Alpine.data('patientPicker', (initial = {}, url = '') => ({
    query: '',
    results: [],
    open: false,
    loading: false,
    error: false,
    searched: false,
    active: -1,
    selected: initial && initial.id ? initial : null,
    timer: null,
    seq: 0,
    onInput() {
        clearTimeout(this.timer);
        const q = this.query.trim();
        this.error = false;
        if (q.length < 2) {
            this.seq++;
            this.results = [];
            this.loading = false;
            this.searched = false;
            this.open = q.length > 0;
            return;
        }
        this.loading = true;
        this.open = true;
        this.timer = setTimeout(() => this.fetch(q), 300);
    },
    async fetch(q) {
        const seq = ++this.seq;
        try {
            const { data } = await window.axios.get(url, { params: { q }, headers: { Accept: 'application/json' } });
            if (seq !== this.seq) return; // a newer search is in flight
            this.results = Array.isArray(data.data) ? data.data : [];
            this.active = this.results.length ? 0 : -1;
            this.searched = true;
        } catch (e) {
            if (seq === this.seq) {
                this.error = true;
                this.results = [];
            }
        } finally {
            if (seq === this.seq) this.loading = false;
        }
    },
    move(delta) {
        if (!this.open || !this.results.length) return;
        this.active = (this.active + delta + this.results.length) % this.results.length;
        this.$nextTick(() => this.$root.querySelector(`[data-option="${this.active}"]`)?.scrollIntoView({ block: 'nearest' }));
    },
    pickActive() {
        if (this.open && this.results[this.active]) this.choose(this.results[this.active]);
    },
    close() {
        this.open = false;
    },
    choose(patient) {
        clearTimeout(this.timer);
        this.seq++;
        this.selected = patient;
        this.open = false;
        this.query = '';
        this.results = [];
        this.$dispatch('patient-selected', patient);
    },
    clear() {
        this.selected = null;
        this.$dispatch('patient-cleared');
        this.$nextTick(() => this.$refs.search?.focus());
    },
}));

/* ----------------------------------------------------------- Test picker */
Alpine.data('testPicker', (tests = [], selected = []) => ({
    tests,
    selected: selected.map(Number),
    filter: '',
    get filtered() {
        const term = this.filter.trim().toLowerCase();
        if (!term) return this.tests;
        return this.tests.filter((t) =>
            [t.name, t.code, t.category].some((v) => (v || '').toLowerCase().includes(term)),
        );
    },
    get groups() {
        const groups = {};
        this.filtered.forEach((t) => {
            (groups[t.category] = groups[t.category] || []).push(t);
        });
        return Object.entries(groups);
    },
    get chosen() {
        return this.selected.map((id) => this.tests.find((t) => t.id === id)).filter(Boolean);
    },
    get total() {
        return this.chosen.reduce((sum, t) => sum + (t.price ? Number(t.price) : 0), 0);
    },
    isSelected(id) {
        return this.selected.includes(id);
    },
    toggle(id) {
        this.selected = this.isSelected(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id];
    },
    remove(id) {
        this.selected = this.selected.filter((x) => x !== id);
    },
}));

/* ------------------------------------------------------ Live list search
 * Used on the filter bar of every list page:
 *   <form x-data="liveFilters" data-target="#results"> … <input name="q" @input="queue()"> …
 * Typing in the search box refreshes only the results block (no page
 * reload, focus stays in the box) after SEARCH_DEBOUNCE_MS. It keeps the
 * filters and sort that are currently applied; changed dropdowns are
 * applied only with the Apply button (a normal form submit).
 */
const SEARCH_DEBOUNCE_MS = 500;

Alpine.data('liveFilters', () => ({
    timer: null,
    loading: false,
    controller: null,
    queue() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.search(), SEARCH_DEBOUNCE_MS);
    },
    async search() {
        const form = this.$root;
        const input = form.querySelector('[name="q"]');
        const params = new URLSearchParams(window.location.search);
        const q = input ? input.value.trim() : '';
        if (q) params.set('q', q);
        else params.delete('q');
        params.delete('page');

        const base = form.getAttribute('action').split('#')[0];
        const query = params.toString();
        const url = query ? `${base}?${query}` : base;

        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: this.controller.signal,
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const selector = form.dataset.target;
            const current = document.querySelector(selector);
            const fresh = doc.querySelector(selector);
            if (!current || !fresh) throw new Error('results block missing');
            current.replaceWith(fresh);
            history.replaceState(history.state, '', url + window.location.hash);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            this.loading = false;
        }
    },
}));

/* -------------------------------------------- Extra rows for table results */
Alpine.data('tableRows', (rows = []) => ({
    rows: rows.map((r) => ({ id: nextId(), label: r.label || '', values: r.values || {} })),
    add() {
        this.rows.push({ id: nextId(), label: '', values: {} });
    },
    remove(id) {
        this.rows = this.rows.filter((r) => r.id !== id);
    },
}));

/* ---------------------------------------------------- Bill totals (paise) */
Alpine.data('billForm', (prices = {}, discountType = 'amount', discountValue = '', paid = 0) => ({
    prices,
    discountType,
    discountValue,
    paid,
    received: '',
    cents(value) {
        const n = parseFloat(String(value ?? '').replace(/,/g, ''));
        return Number.isFinite(n) ? Math.round(n * 100) : 0;
    },
    fmt(cents) {
        return (cents / 100).toFixed(2);
    },
    get subtotal() {
        return Object.values(this.prices).reduce((sum, v) => sum + Math.max(0, this.cents(v)), 0);
    },
    get discount() {
        const value = this.cents(this.discountValue);
        return this.discountType === 'percent' ? Math.round((this.subtotal * value) / 10000) : value;
    },
    get total() {
        return Math.max(0, this.subtotal - this.discount);
    },
    get balance() {
        return Math.max(0, this.total - this.paid - this.cents(this.received));
    },
    get discountError() {
        const value = this.cents(this.discountValue);
        if (value < 0) return 'The discount cannot be negative.';
        if (this.discountType === 'percent' && value > 10000) return 'A percentage discount cannot be more than 100%.';
        if (this.discount > this.subtotal) return 'The discount cannot be more than the subtotal.';
        if (this.subtotal - this.discount < this.paid) return 'The total cannot be less than the amount already paid.';
        return '';
    },
    get receivedError() {
        const value = this.cents(this.received);
        if (value < 0) return 'Enter a positive amount.';
        if (value > this.total) return 'The amount received cannot be more than the total.';
        return '';
    },
}));

Alpine.data('schemaBuilder', schemaBuilder);

Alpine.start();
