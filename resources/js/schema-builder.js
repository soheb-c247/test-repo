/**
 * Alpine component behind the test-definition parameter builder.
 *
 * The builder edits a working copy of the schema (sections → parameters) and
 * serializes it to the hidden `schema` input as JSON.
 *
 * Validation mirrors App\Services\TestSchemaValidator so problems are shown
 * beside the input that caused them before anything is sent. Errors are
 * keyed by `<uid>|<field>` (e.g. `b12|precision`, `b12|reference_ranges.0.max`)
 * so they follow a parameter when it is reordered. After the first failed
 * save the form re-checks itself as you type, so fixed fields clear at once.
 * Errors returned by the server (schema.sections.i.parameters.j.field) are
 * mapped onto the same keys.
 */
const NUMERIC = ['numeric', 'decimal', 'integer'];
const WITH_OPTIONS = ['select', 'multiselect', 'qualitative'];
const KEY = /^[a-z][a-z0-9_]{0,49}$/;
const MAX_PRECISION = 6;

let uid = 0;
const id = () => `b${++uid}`;

export function slugify(text) {
    let slug = String(text || '')
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 50);
    if (slug && !/^[a-z]/.test(slug)) slug = `p_${slug}`.slice(0, 50);
    return slug;
}

const lines = (value) =>
    String(value || '')
        .split(/\r?\n/)
        .map((v) => v.trim())
        .filter(Boolean);

const blank = (v) => v === null || v === undefined || String(v).trim() === '';
const num = (v) => (blank(v) ? null : Number(v));
const isNum = (v) => blank(v) || (String(v).trim() !== '' && Number.isFinite(Number(v)));

/**
 * Parse a formula the same way the server does; returns referenced
 * variables or throws with a readable message.
 */
export function formulaVariables(expression) {
    const src = String(expression);
    const tokens = [];
    const re = /\s*(\d+(?:\.\d+)?|[a-z_][a-z0-9_]*|[-+*/^()])\s*/y;
    let consumed = 0;
    let m;
    while (consumed < src.length && (m = re.exec(src))) {
        tokens.push(m[1]);
        consumed = re.lastIndex;
    }
    if (consumed !== src.length || !tokens.length) throw new Error('use numbers, parameter keys, + - * / ^ and brackets only');

    let expectOperand = true;
    let depth = 0;
    const vars = new Set();
    for (const t of tokens) {
        if (/^\d/.test(t) || /^[a-z_]/.test(t)) {
            if (!expectOperand) throw new Error(`an operator is missing before “${t}”`);
            if (/^[a-z_]/.test(t)) vars.add(t);
            expectOperand = false;
        } else if (t === '(') {
            if (!expectOperand) throw new Error('an operator is missing before “(”');
            depth++;
        } else if (t === ')') {
            if (expectOperand || depth === 0) throw new Error('brackets do not match');
            depth--;
        } else {
            if (expectOperand && t !== '-') throw new Error(`unexpected “${t}”`);
            expectOperand = true;
        }
    }
    if (expectOperand) throw new Error('the formula ends unexpectedly');
    if (depth !== 0) throw new Error('brackets do not match');
    return [...vars];
}

function hydrateParameter(p = {}) {
    return {
        uid: id(),
        expanded: false,
        keyTouched: Boolean(p.key),
        key: p.key || '',
        label: p.label || '',
        type: p.type || 'numeric',
        required: Boolean(p.required),
        unit: p.unit || '',
        precision: p.precision ?? '',
        method: p.method || '',
        help: p.help || '',
        reference_text: p.reference_text || '',
        default: Array.isArray(p.default) ? p.default.join('\n') : p.default ?? '',
        show_in_pdf: p.show_in_pdf !== false,
        options: Array.isArray(p.options) ? p.options.join('\n') : p.options || '',
        reference_ranges: (p.reference_ranges || []).map((r) => ({
            uid: id(),
            min: r.min ?? '',
            max: r.max ?? '',
            sex: r.sex || 'any',
            age_min: r.age_min ?? '',
            age_max: r.age_max ?? '',
            label: r.label || '',
        })),
        min_value: p.min_value ?? '',
        max_value: p.max_value ?? '',
        formula: p.formula || '',
        max_length: p.max_length ?? '',
        columns: (p.columns || []).map((c) => ({
            uid: id(),
            key: c.key || '',
            label: c.label || '',
            type: c.type || 'text',
            options: Array.isArray(c.options) ? c.options.join('\n') : c.options || '',
        })),
        rows: (p.rows || []).map((r) => r.label).join('\n'),
        rowKeys: Object.fromEntries((p.rows || []).filter((r) => r.key).map((r) => [r.label, r.key])),
        allow_add_rows: Boolean(p.allow_add_rows),
        row_header: p.row_header || '',
    };
}

function hydrateSection(s = {}) {
    return {
        uid: id(),
        key: s.key || '',
        keyTouched: Boolean(s.key),
        title: s.title || '',
        parameters: (s.parameters || []).map(hydrateParameter),
    };
}

function serializeParameter(p) {
    const out = {
        key: p.key || slugify(p.label),
        label: p.label.trim(),
        type: p.type,
        required: p.required,
        unit: p.unit.trim() || null,
        method: p.method.trim() || null,
        help: p.help.trim() || null,
        reference_text: p.reference_text.trim() || null,
        show_in_pdf: p.show_in_pdf,
    };

    if (p.type === 'multiselect') {
        out.default = lines(p.default);
        if (!out.default.length) out.default = null;
    } else {
        out.default = String(p.default ?? '').trim() || null;
    }

    if (WITH_OPTIONS.includes(p.type)) out.options = lines(p.options);

    if (NUMERIC.includes(p.type)) {
        out.precision = blank(p.precision) ? null : Number(p.precision);
        out.min_value = num(p.min_value);
        out.max_value = num(p.max_value);
        out.formula = String(p.formula).trim() || null;
        out.reference_ranges = p.reference_ranges.map((r) => ({
            min: num(r.min),
            max: num(r.max),
            sex: r.sex || 'any',
            age_min: num(r.age_min),
            age_max: num(r.age_max),
            label: r.label.trim() || null,
        }));
    }

    if (['text', 'textarea'].includes(p.type) && !blank(p.max_length)) {
        out.max_length = Number(p.max_length);
    }

    if (p.type === 'table') {
        out.columns = p.columns.map((c) => ({
            key: c.key || slugify(c.label),
            label: c.label.trim(),
            type: c.type,
            ...(c.type === 'select' ? { options: lines(c.options) } : {}),
        }));
        // Keep existing row keys stable when labels are unchanged.
        out.rows = lines(p.rows).map((label) => (p.rowKeys?.[label] ? { key: p.rowKeys[label], label } : { label }));
        out.allow_add_rows = p.allow_add_rows;
        out.row_header = p.row_header.trim() || null;
    }

    return out;
}

export default (initial = {}, types = {}, serverErrors = {}) => ({
    sections: (initial.sections || []).map(hydrateSection),
    types,
    fieldErrors: {},
    errors: [],
    attempted: false,
    showJson: false,
    jsonDraft: '',
    jsonError: '',

    init() {
        if (!this.sections.length) this.addSection();
        this.applyServerErrors(serverErrors);
    },

    get schema() {
        return {
            schema_version: 1,
            sections: this.sections.map((s) => ({
                key: s.key || slugify(s.title) || undefined,
                title: s.title.trim(),
                parameters: s.parameters.map(serializeParameter),
            })),
        };
    },

    get json() {
        return JSON.stringify(this.schema);
    },

    get parameterCount() {
        return this.sections.reduce((n, s) => n + s.parameters.length, 0);
    },

    get errorCount() {
        return Object.keys(this.fieldErrors).length || this.errors.length;
    },

    isNumeric(p) {
        return NUMERIC.includes(p.type);
    },
    hasOptions(p) {
        return WITH_OPTIONS.includes(p.type);
    },

    // ----- error helpers used by the template
    err(item, field) {
        return this.fieldErrors[`${item.uid}|${field}`] || '';
    },
    hasErrors(item) {
        const prefix = `${item.uid}|`;
        return Object.keys(this.fieldErrors).some((k) => k.startsWith(prefix));
    },
    rangeErrors(p, ri) {
        return ['min', 'max', 'sex', 'age_min', 'age_max', 'label']
            .map((f) => this.err(p, `reference_ranges.${ri}.${f}`))
            .filter(Boolean);
    },
    columnError(p, ci) {
        return ['label', 'type', 'options'].map((f) => this.err(p, `columns.${ci}.${f}`)).find(Boolean) || '';
    },
    revalidate() {
        if (this.attempted) this.validate();
    },

    // ----- sections
    addSection() {
        const section = hydrateSection({ title: this.sections.length ? '' : 'Results' });
        section.parameters.push(this.newParameter());
        this.sections.push(section);
        this.revalidate();
    },
    removeSection(index) {
        this.sections.splice(index, 1);
        this.revalidate();
    },
    moveSection(index, delta) {
        const target = index + delta;
        if (target < 0 || target >= this.sections.length) return;
        const [s] = this.sections.splice(index, 1);
        this.sections.splice(target, 0, s);
    },
    sectionTitleChanged(section) {
        if (!section.keyTouched) section.key = slugify(section.title);
    },

    // ----- parameters
    newParameter() {
        const p = hydrateParameter({ type: 'numeric', required: true });
        p.expanded = true;
        return p;
    },
    addParameter(section) {
        section.parameters.push(this.newParameter());
        this.revalidate();
    },
    duplicateParameter(section, index) {
        const copy = hydrateParameter(serializeParameter(section.parameters[index]));
        copy.key = this.uniqueKey(`${copy.key}_copy`);
        copy.label = `${copy.label} (copy)`;
        copy.expanded = true;
        section.parameters.splice(index + 1, 0, copy);
        this.revalidate();
    },
    removeParameter(section, index) {
        section.parameters.splice(index, 1);
        this.revalidate();
    },
    moveParameter(sectionIndex, index, delta) {
        const section = this.sections[sectionIndex];
        const target = index + delta;
        if (target >= 0 && target < section.parameters.length) {
            const [p] = section.parameters.splice(index, 1);
            section.parameters.splice(target, 0, p);
            return;
        }
        const neighbour = this.sections[sectionIndex + delta];
        if (!neighbour) return;
        const [p] = section.parameters.splice(index, 1);
        if (delta < 0) neighbour.parameters.push(p);
        else neighbour.parameters.unshift(p);
        this.revalidate();
    },
    labelChanged(p) {
        if (!p.keyTouched) p.key = this.uniqueKey(slugify(p.label), p);
    },
    uniqueKey(base, self = null) {
        if (!base) return '';
        const taken = new Set();
        this.sections.forEach((s) => s.parameters.forEach((p) => p !== self && p.key && taken.add(p.key)));
        let key = base.slice(0, 50);
        let n = 2;
        while (taken.has(key)) key = `${base.slice(0, 46)}_${n++}`;
        return key;
    },
    typeChanged(p) {
        if (p.type === 'table' && !p.columns.length) {
            p.columns.push({ uid: id(), key: 'result', label: 'Result', type: 'text', options: '' });
        }
        if (p.type === 'qualitative' && !p.options) p.options = 'Negative\nPositive';
        if (p.type === 'integer') p.precision = 0;
        this.revalidate();
    },
    addRange(p) {
        p.reference_ranges.push({ uid: id(), min: '', max: '', sex: 'any', age_min: '', age_max: '', label: '' });
    },
    removeRange(p, ri) {
        p.reference_ranges.splice(ri, 1);
        this.revalidate();
    },
    addColumn(p) {
        p.columns.push({ uid: id(), key: '', label: '', type: 'text', options: '' });
    },
    removeColumn(p, ci) {
        p.columns.splice(ci, 1);
        this.revalidate();
    },
    summary(p) {
        const bits = [this.types[p.type] ? this.types[p.type].split(' (')[0] : p.type];
        if (p.unit) bits.push(p.unit);
        const r = p.reference_ranges[0];
        if (r && (r.min !== '' || r.max !== '')) {
            bits.push(`${r.min === '' ? '≤' : r.min}${r.min !== '' && r.max !== '' ? ' – ' : ''}${r.max === '' ? '' : r.max}`);
            if (p.reference_ranges.length > 1) bits.push(`+${p.reference_ranges.length - 1} ranges`);
        } else if (p.reference_text) {
            bits.push(p.reference_text);
        }
        if (p.formula) bits.push('calculated');
        return bits.join(' · ');
    },

    // ----- validation (mirrors TestSchemaValidator)
    validate() {
        const fe = {};
        const summary = [];
        const add = (item, field, message, where) => {
            const k = `${item.uid}|${field}`;
            if (!fe[k]) fe[k] = message;
            summary.push(`${where}: ${message.charAt(0).toLowerCase()}${message.slice(1)}`);
        };
        const tooLong = (item, field, value, max, what, where) => {
            if (String(value ?? '').trim().length > max) add(item, field, `${what} may not be longer than ${max} characters`, where);
        };

        const sectionKeys = new Map();
        const paramKeys = new Map();
        const numericKeys = new Set();
        const formulas = [];
        let total = 0;

        if (!this.sections.length) summary.push('Add at least one section with at least one parameter.');
        if (this.sections.length > 30) summary.push('A test may have at most 30 sections.');

        this.sections.forEach((s, si) => {
            const sName = s.title.trim() ? `Section “${s.title.trim()}”` : `Section ${si + 1}`;
            tooLong(s, 'title', s.title, 120, 'Title', sName);
            const sKey = s.key || slugify(s.title) || `section_${si + 1}`;
            if (sectionKeys.has(sKey)) add(s, 'title', `Another section already uses this title`, sName);
            sectionKeys.set(sKey, true);
            if (!s.parameters.length) add(s, 'parameters', 'Add at least one parameter to this section', sName);

            s.parameters.forEach((p, pi) => {
                total++;
                const where = `${s.title.trim() || `Section ${si + 1}`} › ${p.label.trim() || `Parameter ${pi + 1}`}`;
                const key = p.key || slugify(p.label);

                if (!p.label.trim()) add(p, 'label', 'Label is required', where);
                tooLong(p, 'label', p.label, 150, 'Label', where);
                if (!KEY.test(key)) add(p, 'key', 'Key must start with a letter and use only a–z, 0–9 and _ (max 50)', where);
                else if (paramKeys.has(key)) add(p, 'key', `Key “${key}” is already used by ${paramKeys.get(key)}`, where);
                else paramKeys.set(key, p.label.trim() ? `“${p.label.trim()}”` : 'another parameter');
                if (!this.types[p.type]) add(p, 'type', 'Choose an input type', where);
                tooLong(p, 'unit', p.unit, 40, 'Unit', where);
                tooLong(p, 'method', p.method, 191, 'Method', where);
                tooLong(p, 'help', p.help, 500, 'Help text', where);
                tooLong(p, 'reference_text', p.reference_text, 255, 'Reference text', where);
                if (p.type !== 'multiselect') tooLong(p, 'default', p.default, 255, 'Default value', where);

                if (this.hasOptions(p)) {
                    const opts = lines(p.options);
                    if (!opts.length) add(p, 'options', 'Add at least one option (one per line)', where);
                    const dup = opts.find((o, i) => opts.indexOf(o) !== i);
                    if (dup) add(p, 'options', `Option “${dup}” is listed twice`, where);
                    if (opts.some((o) => o.length > 100)) add(p, 'options', 'Each option may be at most 100 characters', where);
                    if (opts.length > 100) add(p, 'options', 'At most 100 options are allowed', where);
                    const defaults = p.type === 'multiselect' ? lines(p.default) : blank(p.default) ? [] : [String(p.default).trim()];
                    const bad = defaults.find((d) => !opts.includes(d));
                    if (bad) add(p, 'default', `“${bad}” is not one of the options`, where);
                }

                if (this.isNumeric(p)) {
                    numericKeys.add(key);
                    if (!blank(p.precision)) {
                        const v = Number(p.precision);
                        if (!Number.isInteger(v) || v < 0 || v > MAX_PRECISION) add(p, 'precision', `Decimal places must be a whole number from 0 to ${MAX_PRECISION}`, where);
                    }
                    if (!isNum(p.min_value)) add(p, 'min_value', 'Minimum allowed entry must be a number', where);
                    if (!isNum(p.max_value)) add(p, 'max_value', 'Maximum allowed entry must be a number', where);
                    if (!blank(p.min_value) && !blank(p.max_value) && isNum(p.min_value) && isNum(p.max_value) && Number(p.min_value) > Number(p.max_value)) {
                        add(p, 'max_value', 'Maximum allowed entry must be greater than the minimum', where);
                    }
                    if (p.reference_ranges.length > 20) add(p, 'reference_ranges', 'At most 20 reference intervals are allowed', where);
                    p.reference_ranges.forEach((r, ri) => {
                        const rw = `${where} (interval ${ri + 1})`;
                        const f = (name) => `reference_ranges.${ri}.${name}`;
                        ['min', 'max', 'age_min', 'age_max'].forEach((n) => {
                            if (!isNum(r[n])) add(p, f(n), `${{ min: 'Low limit', max: 'High limit', age_min: 'Age from', age_max: 'Age to' }[n]} must be a number`, rw);
                        });
                        if (blank(r.min) && blank(r.max)) add(p, f('min'), 'Enter a low limit, a high limit or both', rw);
                        if (!blank(r.min) && !blank(r.max) && Number(r.min) > Number(r.max)) add(p, f('max'), 'High limit must be greater than the low limit', rw);
                        if (!blank(r.age_min) && Number(r.age_min) < 0) add(p, f('age_min'), 'Age cannot be negative', rw);
                        if (!blank(r.age_max) && Number(r.age_max) < 0) add(p, f('age_max'), 'Age cannot be negative', rw);
                        if (!blank(r.age_min) && !blank(r.age_max) && Number(r.age_min) >= Number(r.age_max)) add(p, f('age_max'), '“Age to” must be greater than “Age from”', rw);
                        tooLong(p, f('label'), r.label, 60, 'Label', rw);
                    });
                    if (String(p.formula).trim()) {
                        if (String(p.formula).trim().length > 255) add(p, 'formula', 'Formula may not be longer than 255 characters', where);
                        formulas.push({ p, key, where });
                    }
                }

                if (['text', 'textarea'].includes(p.type) && !blank(p.max_length)) {
                    const limit = p.type === 'textarea' ? 5000 : 255;
                    const v = Number(p.max_length);
                    if (!Number.isInteger(v) || v < 1 || v > limit) add(p, 'max_length', `Max length must be a whole number from 1 to ${limit}`, where);
                }

                if (p.type === 'table') {
                    if (!p.columns.length) add(p, 'columns', 'Add at least one result column', where);
                    if (p.columns.length > 8) add(p, 'columns', 'A table may have at most 8 columns', where);
                    const colKeys = new Set();
                    p.columns.forEach((c, ci) => {
                        const cw = `${where} (column ${ci + 1})`;
                        const ck = c.key || slugify(c.label);
                        if (!c.label.trim()) add(p, `columns.${ci}.label`, 'Column label is required', cw);
                        else if (colKeys.has(ck)) add(p, `columns.${ci}.label`, 'Two columns have the same label', cw);
                        colKeys.add(ck);
                        tooLong(p, `columns.${ci}.label`, c.label, 60, 'Column label', cw);
                        if (c.type === 'select' && !lines(c.options).length) add(p, `columns.${ci}.options`, 'Add the dropdown options (one per line)', cw);
                    });
                    const rows = lines(p.rows);
                    if (!rows.length && !p.allow_add_rows) add(p, 'rows', 'Add rows or allow rows to be added during result entry', where);
                    if (rows.length > 100) add(p, 'rows', 'A table may have at most 100 predefined rows', where);
                    tooLong(p, 'row_header', p.row_header, 60, 'First column heading', where);
                }
            });
        });

        if (total > 200) summary.push('A test may have at most 200 parameters.');

        formulas.forEach(({ p, key, where }) => {
            try {
                formulaVariables(String(p.formula).trim()).forEach((v) => {
                    if (v === key) add(p, 'formula', 'A formula cannot use its own parameter', where);
                    else if (!numericKeys.has(v)) add(p, 'formula', `“${v}” is not the key of a numeric parameter in this test`, where);
                    else if (formulas.some((f) => f.key === v)) add(p, 'formula', `“${v}” is itself calculated`, where);
                });
            } catch (e) {
                add(p, 'formula', `Invalid formula — ${e.message}`, where);
            }
        });

        this.fieldErrors = fe;
        this.errors = [...new Set(summary)];
        return this.errors.length === 0;
    },

    /**
     * Map server errors (schema.sections.i.parameters.j.field) onto the
     * matching inputs and open the affected parameters.
     */
    applyServerErrors(serverErrors) {
        const entries = Object.entries(serverErrors || {});
        if (!entries.length) return;
        const fe = {};
        entries.forEach(([path, messages]) => {
            const m = path.match(/^schema\.sections\.(\d+)(?:\.parameters\.(\d+))?\.(.+)$/);
            if (!m) return;
            const section = this.sections[Number(m[1])];
            if (!section) return;
            const target = m[2] !== undefined ? section.parameters[Number(m[2])] : section;
            if (!target) return;
            fe[`${target.uid}|${m[3]}`] = Array.isArray(messages) ? messages[0] : messages;
            if (m[2] !== undefined) target.expanded = true;
        });
        this.fieldErrors = fe;
        this.attempted = true;
        this.$nextTick(() => this.scrollToFirstError());
    },

    scrollToFirstError() {
        const el = this.$root.querySelector('[aria-invalid="true"], [data-field-error]:not([style*="display: none"])');
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof el.focus === 'function' && el.matches('input, select, textarea')) el.focus({ preventScroll: true });
        } else {
            this.$refs.builderErrors?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    },

    submit(event) {
        this.attempted = true;
        if (!this.validate()) {
            event.preventDefault();
            delete event.target.dataset.submitting;
            event.target.querySelectorAll('button[type=submit]').forEach((b) => b.removeAttribute('aria-busy'));
            // Open every parameter that has a problem, then jump to the first one.
            this.sections.forEach((s) => s.parameters.forEach((p) => { if (this.hasErrors(p)) p.expanded = true; }));
            this.$nextTick(() => this.scrollToFirstError());
            return;
        }
        this.$refs.schemaInput.value = this.json;
    },

    // ----- raw JSON editing (advanced)
    openJson() {
        this.jsonDraft = JSON.stringify(this.schema, null, 2);
        this.jsonError = '';
        this.showJson = true;
    },
    applyJson() {
        try {
            const parsed = JSON.parse(this.jsonDraft);
            if (!parsed || !Array.isArray(parsed.sections)) throw new Error('JSON must contain a "sections" array.');
            this.sections = parsed.sections.map(hydrateSection);
            this.showJson = false;
            this.attempted = true;
            this.validate();
        } catch (e) {
            this.jsonError = e.message;
        }
    },
});
