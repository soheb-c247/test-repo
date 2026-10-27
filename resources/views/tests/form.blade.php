@php
    $editing = $test->exists;
    $schema = $test->schema ?? ['schema_version' => 1, 'sections' => []];
    if (old('schema')) {
        $decoded = json_decode(old('schema'), true);
        if (is_array($decoded)) {
            $schema = $decoded;
        }
    }
    $usage = $usage ?? 0;
    // Field-level schema errors from the server (schema.sections.i.parameters.j.field).
    $schemaErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'schema.'))->all();
    $schemaSummary = $errors->get('schema');
@endphp

{{--
    Field error helper: every builder input gets
      :class="err(item,'field') && 'input-error'"  :aria-invalid="err(item,'field') ? 'true' : null"
    and a <p data-field-error> right below it.
--}}
<x-app-layout :title="$editing ? 'Edit '.$test->name : 'New test'"
              :breadcrumbs="$editing ? ['Tests' => route('tests.index'), $test->code => route('tests.show', $test), 'Edit' => null] : ['Tests' => route('tests.index'), 'New test' => null]">

    @if ($editing && $usage > 0)
        <div class="mb-6 flex gap-3 rounded-xl border border-navy-200 bg-navy-50 p-4 text-sm text-navy-900">
            <x-icon name="shield" class="mt-0.5 h-5 w-5 shrink-0" />
            <p>This test is used by <strong>{{ $usage }}</strong> report {{ Str::plural('item', $usage) }}. Saving changes creates <strong>version {{ $test->version + 1 }}</strong>; existing reports keep the parameters, units and reference intervals of the version they were created with.</p>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('tests.update', $test) : route('tests.store') }}" novalidate
          x-data="schemaBuilder(@js($schema), @js($types), @js($schemaErrors))"
          @submit="submit($event)" @input.debounce.200ms="revalidate()" @change="revalidate()">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="schema" x-ref="schemaInput" :value="json" value="{{ json_encode($schema) }}">

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- General information --}}
            <section class="card xl:col-span-1 xl:self-start" aria-labelledby="general-title">
                <div class="card-header"><h2 id="general-title" class="card-title">General information</h2></div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-1">
                            <label for="code" class="label">Code <span class="text-red-500">*</span></label>
                            <input id="code" name="code" value="{{ old('code', $test->code) }}" maxlength="32" required
                                   @class(['input font-mono uppercase', 'input-error' => $errors->has('code')]) placeholder="CBC"
                                   @error('code') aria-invalid="true" @enderror>
                        </div>
                        <div class="col-span-2">
                            <label for="name" class="label">Test name <span class="text-red-500">*</span></label>
                            <input id="name" name="name" value="{{ old('name', $test->name) }}" maxlength="150" required @class(['input', 'input-error' => $errors->has('name')])
                                   @error('name') aria-invalid="true" @enderror>
                        </div>
                        @error('code')<p class="field-error col-span-3 -mt-2">{{ $message }}</p>@enderror
                        @error('name')<p class="field-error col-span-3 -mt-2">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="category" class="label">Category <span class="text-red-500">*</span></label>
                        <input id="category" name="category" list="category-options" value="{{ old('category', $test->category) }}" maxlength="80" required
                               @class(['input', 'input-error' => $errors->has('category')]) placeholder="Choose or type a new category">
                        <datalist id="category-options">
                            @foreach ($categories as $category)<option value="{{ $category }}">@endforeach
                        </datalist>
                        @error('category')<p class="field-error">{{ $message }}</p>@else<p class="help">Pick a suggestion or type a new category.</p>@enderror
                    </div>
                    <div>
                        <label for="sample_type" class="label">Specimen / sample type</label>
                        <input id="sample_type" name="sample_type" value="{{ old('sample_type', $test->sample_type) }}" maxlength="120" @class(['input', 'input-error' => $errors->has('sample_type')]) placeholder="e.g. Whole blood (EDTA)">
                        @error('sample_type')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="method" class="label">Methodology</label>
                        <input id="method" name="method" value="{{ old('method', $test->method) }}" maxlength="191" @class(['input', 'input-error' => $errors->has('method')]) placeholder="e.g. Electrical impedance">
                        @error('method')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="description" class="label">Short description</label>
                        <input id="description" name="description" value="{{ old('description', $test->description) }}" maxlength="500" @class(['input', 'input-error' => $errors->has('description')])>
                        @error('description')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="price" class="label">Price ({{ config('lab.currency') }})</label>
                            <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $test->price) }}" @class(['input', 'input-error' => $errors->has('price')])>
                            @error('price')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="turnaround_hours" class="label">Turnaround (hours)</label>
                            <input id="turnaround_hours" name="turnaround_hours" type="number" min="1" max="2000" value="{{ old('turnaround_hours', $test->turnaround_hours) }}" @class(['input', 'input-error' => $errors->has('turnaround_hours')])>
                            @error('turnaround_hours')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="preparation_instructions" class="label">Patient preparation</label>
                        <textarea id="preparation_instructions" name="preparation_instructions" rows="2" maxlength="2000" class="input" placeholder="e.g. 8–10 hours fasting">{{ old('preparation_instructions', $test->preparation_instructions) }}</textarea>
                    </div>
                    <div>
                        <label for="collection_instructions" class="label">Sample collection instructions</label>
                        <textarea id="collection_instructions" name="collection_instructions" rows="2" maxlength="2000" class="input">{{ old('collection_instructions', $test->collection_instructions) }}</textarea>
                    </div>
                    <div>
                        <label for="reporting_notes" class="label">Reporting notes / interpretation</label>
                        <textarea id="reporting_notes" name="reporting_notes" rows="6" maxlength="5000" @class(['input font-mono text-xs', 'input-error' => $errors->has('reporting_notes')])>{{ old('reporting_notes', $test->reporting_notes) }}</textarea>
                        @error('reporting_notes')<p class="field-error">{{ $message }}</p>@else<p class="help">Printed under the results. Lines written as <code>a | b | c</code> become a table (first line = header). Separate paragraphs with a blank line.</p>@enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="checkbox" @checked(old('is_active', $test->is_active))>
                        Active — available for new reports
                    </label>
                </div>
            </section>

            {{-- Parameter builder --}}
            <section class="space-y-4 xl:col-span-2" aria-labelledby="builder-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="builder-title" class="text-base font-semibold text-navy-900">Parameters</h2>
                        <p class="text-xs text-slate-500"><span x-text="sections.length"></span> section(s) · <span x-text="parameterCount"></span> parameter(s). Keys identify values permanently — change them only before the test is used.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-sm btn-ghost" @click="openJson()"><x-icon name="code" /> JSON</button>
                        <button type="button" class="btn btn-sm btn-secondary" @click="addSection()"><x-icon name="plus" /> Section</button>
                    </div>
                </div>

                {{-- Error summary: count + jump link; details sit beside each field --}}
                <div x-ref="builderErrors" x-cloak x-show="errorCount > 0" class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                    <div class="flex gap-2">
                        <x-icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <p class="font-semibold"><span x-text="errorCount"></span> <span x-text="errorCount === 1 ? 'field needs' : 'fields need'"></span> fixing — each problem is shown in red next to its field.</p>
                            <details class="mt-1 text-xs text-red-700">
                                <summary class="cursor-pointer select-none font-medium">Show list</summary>
                                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                    <template x-for="e in errors"><li x-text="e"></li></template>
                                    @foreach ($schemaSummary as $message)
                                        <li x-show="!errors.length">{{ $message }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" @click="scrollToFirstError()">Go to first problem</button>
                </div>
                @if ($schemaSummary && ! $schemaErrors)
                    {{-- Schema-wide problems that are not tied to a single field --}}
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                        <ul class="list-disc pl-5">@foreach ($schemaSummary as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                @endif

                <template x-for="(section, si) in sections" :key="section.uid">
                    <div class="card" :class="hasErrors(section) && 'border-red-300'">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-brand-50 text-[11px] font-bold text-brand-700" x-text="si + 1"></span>
                                <label class="sr-only" :for="'st-' + section.uid">Section title</label>
                                <input :id="'st-' + section.uid" type="text" x-model="section.title" @input="sectionTitleChanged(section)" maxlength="120"
                                       class="input input-sm min-w-[12rem] flex-1 font-semibold" placeholder="Section title (e.g. Differential count)"
                                       :class="err(section, 'title') && 'input-error'" :aria-invalid="err(section, 'title') ? 'true' : null">
                                <span class="hidden font-mono text-[11px] text-slate-400 sm:inline" x-text="section.key"></span>
                                <div class="ml-auto flex items-center">
                                    <button type="button" class="icon-btn" @click="moveSection(si, -1)" :disabled="si === 0" title="Move section up" aria-label="Move section up"><x-icon name="chevron-up" /></button>
                                    <button type="button" class="icon-btn" @click="moveSection(si, 1)" :disabled="si === sections.length - 1" title="Move section down" aria-label="Move section down"><x-icon name="chevron-down" /></button>
                                    <button type="button" class="icon-btn text-red-500 hover:bg-red-50 hover:text-red-700" @click="if (!section.parameters.length || confirm('Remove this section and its parameters?')) removeSection(si)" title="Remove section" aria-label="Remove section"><x-icon name="trash" /></button>
                                </div>
                            </div>
                            <p class="field-error pl-8" data-field-error x-show="err(section, 'title')" x-text="err(section, 'title')"></p>
                            <p class="field-error pl-8" data-field-error x-show="err(section, 'parameters')" x-text="err(section, 'parameters')"></p>
                        </div>

                        <ul class="divide-y divide-slate-100">
                            <template x-for="(p, pi) in section.parameters" :key="p.uid">
                                <li :class="hasErrors(p) && 'border-l-4 border-l-red-400'">
                                    <div class="flex flex-wrap items-center gap-2 px-4 py-2.5" :class="p.expanded && 'bg-slate-50'">
                                        <button type="button" class="icon-btn" @click="p.expanded = !p.expanded" :aria-expanded="p.expanded" :aria-label="(p.expanded ? 'Collapse ' : 'Expand ') + (p.label || 'parameter')">
                                            <x-icon name="chevron-right" class="h-4 w-4 transition" x-bind:class="p.expanded && 'rotate-90'" />
                                        </button>
                                        <div class="min-w-0 flex-1 cursor-pointer" @click="p.expanded = !p.expanded">
                                            <p class="flex items-center gap-2 truncate text-sm font-medium text-slate-900">
                                                <span x-text="p.label || 'Untitled parameter'"></span>
                                                <span x-show="p.required" class="text-red-500" title="Required">*</span>
                                                <span x-show="hasErrors(p)" class="badge badge-danger"><x-icon name="alert" class="h-3 w-3" /> Needs fixing</span>
                                            </p>
                                            <p class="truncate text-xs text-slate-500"><span class="font-mono" x-text="p.key"></span> · <span x-text="summary(p)"></span></p>
                                        </div>
                                        <div class="flex items-center">
                                            <button type="button" class="icon-btn" @click="moveParameter(si, pi, -1)" :disabled="si === 0 && pi === 0" title="Move up" aria-label="Move parameter up"><x-icon name="chevron-up" /></button>
                                            <button type="button" class="icon-btn" @click="moveParameter(si, pi, 1)" :disabled="si === sections.length - 1 && pi === section.parameters.length - 1" title="Move down" aria-label="Move parameter down"><x-icon name="chevron-down" /></button>
                                            <button type="button" class="icon-btn" @click="duplicateParameter(section, pi)" title="Duplicate" aria-label="Duplicate parameter"><x-icon name="copy" /></button>
                                            <button type="button" class="icon-btn text-red-500 hover:bg-red-50 hover:text-red-700" @click="removeParameter(section, pi)" title="Remove" aria-label="Remove parameter"><x-icon name="trash" /></button>
                                        </div>
                                    </div>

                                    <div x-show="p.expanded" x-transition.opacity.duration.150ms class="space-y-4 border-t border-slate-100 bg-slate-50 px-4 pb-4 pt-3 sm:pl-14">
                                        <div class="grid gap-3 sm:grid-cols-6">
                                            <div class="sm:col-span-3">
                                                <label class="label" :for="'pl-' + p.uid">Label *</label>
                                                <input :id="'pl-' + p.uid" type="text" class="input input-sm" x-model="p.label" @input="labelChanged(p)" maxlength="150"
                                                       :class="err(p, 'label') && 'input-error'" :aria-invalid="err(p, 'label') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'label')" x-text="err(p, 'label')"></p>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <label class="label" :for="'pt-' + p.uid">Input type</label>
                                                <select :id="'pt-' + p.uid" class="input input-sm" x-model="p.type" @change="typeChanged(p)"
                                                        :class="err(p, 'type') && 'input-error'" :aria-invalid="err(p, 'type') ? 'true' : null">
                                                    <template x-for="(label, value) in types" :key="value"><option :value="value" x-text="label" :selected="value === p.type"></option></template>
                                                </select>
                                                <p class="field-error" data-field-error x-show="err(p, 'type')" x-text="err(p, 'type')"></p>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="label" :for="'pk-' + p.uid">Key</label>
                                                <input :id="'pk-' + p.uid" type="text" class="input input-sm font-mono" x-model="p.key" @input="p.keyTouched = true" maxlength="50"
                                                       :class="err(p, 'key') && 'input-error'" :aria-invalid="err(p, 'key') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'key')" x-text="err(p, 'key')"></p>
                                            </div>
                                            <div class="sm:col-span-2" x-show="p.type !== 'table'">
                                                <label class="label" :for="'pu-' + p.uid">Unit</label>
                                                <input :id="'pu-' + p.uid" type="text" class="input input-sm" x-model="p.unit" maxlength="40" placeholder="e.g. mg/dL"
                                                       :class="err(p, 'unit') && 'input-error'" :aria-invalid="err(p, 'unit') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'unit')" x-text="err(p, 'unit')"></p>
                                            </div>
                                            <div class="sm:col-span-2" x-show="isNumeric(p)">
                                                <label class="label" :for="'pp-' + p.uid">Decimal places (0–6)</label>
                                                <input :id="'pp-' + p.uid" type="number" min="0" max="6" step="1" class="input input-sm" x-model="p.precision" :disabled="p.type === 'integer'" placeholder="As entered"
                                                       :class="err(p, 'precision') && 'input-error'" :aria-invalid="err(p, 'precision') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'precision')" x-text="err(p, 'precision')"></p>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <label class="label" :for="'pm-' + p.uid">Method</label>
                                                <input :id="'pm-' + p.uid" type="text" class="input input-sm" x-model="p.method" maxlength="191"
                                                       :class="err(p, 'method') && 'input-error'" :aria-invalid="err(p, 'method') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'method')" x-text="err(p, 'method')"></p>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <label class="label" :for="'pr-' + p.uid">Reference text</label>
                                                <input :id="'pr-' + p.uid" type="text" class="input input-sm" x-model="p.reference_text" maxlength="255" placeholder="Shown instead of numeric ranges, e.g. Negative"
                                                       :class="err(p, 'reference_text') && 'input-error'" :aria-invalid="err(p, 'reference_text') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'reference_text')" x-text="err(p, 'reference_text')"></p>
                                            </div>
                                            <div class="sm:col-span-3">
                                                <label class="label" :for="'ph-' + p.uid">Help text</label>
                                                <input :id="'ph-' + p.uid" type="text" class="input input-sm" x-model="p.help" maxlength="500" placeholder="Shown during result entry"
                                                       :class="err(p, 'help') && 'input-error'" :aria-invalid="err(p, 'help') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'help')" x-text="err(p, 'help')"></p>
                                            </div>
                                            <div class="sm:col-span-3" x-show="!['table', 'boolean'].includes(p.type) && !p.formula">
                                                <label class="label" :for="'pd-' + p.uid">Default value</label>
                                                <input :id="'pd-' + p.uid" type="text" class="input input-sm" x-model="p.default" maxlength="255" placeholder="Leave blank to start empty"
                                                       :class="err(p, 'default') && 'input-error'" :aria-invalid="err(p, 'default') ? 'true' : null">
                                                <p class="field-error" data-field-error x-show="err(p, 'default')" x-text="err(p, 'default')"></p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-5 text-sm text-slate-700">
                                            <label class="flex items-center gap-2"><input type="checkbox" class="checkbox" x-model="p.required" :disabled="!!p.formula"> Required to finalize</label>
                                            <label class="flex items-center gap-2"><input type="checkbox" class="checkbox" x-model="p.show_in_pdf"> Show on report</label>
                                        </div>

                                        <div x-show="hasOptions(p)">
                                            <label class="label" :for="'po-' + p.uid">Options (one per line)</label>
                                            <textarea :id="'po-' + p.uid" rows="3" class="input input-sm font-mono" x-model="p.options" placeholder="Negative&#10;Positive"
                                                      :class="err(p, 'options') && 'input-error'" :aria-invalid="err(p, 'options') ? 'true' : null"></textarea>
                                            <p class="field-error" data-field-error x-show="err(p, 'options')" x-text="err(p, 'options')"></p>
                                        </div>

                                        <div x-show="['text', 'textarea'].includes(p.type)" class="max-w-[14rem]">
                                            <label class="label" :for="'px-' + p.uid">Max length</label>
                                            <input :id="'px-' + p.uid" type="number" min="1" :max="p.type === 'textarea' ? 5000 : 255" class="input input-sm" x-model="p.max_length"
                                                   :class="err(p, 'max_length') && 'input-error'" :aria-invalid="err(p, 'max_length') ? 'true' : null">
                                            <p class="field-error" data-field-error x-show="err(p, 'max_length')" x-text="err(p, 'max_length')"></p>
                                        </div>

                                        <template x-if="isNumeric(p)">
                                            <div class="space-y-3">
                                                <div class="rounded-lg border bg-white" :class="err(p, 'reference_ranges') ? 'border-red-300' : 'border-slate-200'">
                                                    <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Biological reference intervals</p>
                                                        <button type="button" class="btn btn-sm btn-ghost" @click="addRange(p)"><x-icon name="plus" /> Interval</button>
                                                    </div>
                                                    <p x-show="!p.reference_ranges.length" class="px-3 py-3 text-xs text-slate-500">No interval configured — results will not be flagged.</p>
                                                    <p class="field-error px-3" data-field-error x-show="err(p, 'reference_ranges')" x-text="err(p, 'reference_ranges')"></p>
                                                    <div class="relative overflow-x-auto" x-show="p.reference_ranges.length">
                                                        <table class="w-full text-xs">
                                                            <thead class="text-left text-[11px] uppercase text-slate-500">
                                                                <tr><th class="px-2 py-1.5">Low</th><th class="px-2 py-1.5">High</th><th class="px-2 py-1.5">Sex</th><th class="px-2 py-1.5">Age from (y)</th><th class="px-2 py-1.5">Age to (y)</th><th class="px-2 py-1.5">Label</th><th></th></tr>
                                                            </thead>
                                                            <template x-for="(r, ri) in p.reference_ranges" :key="r.uid">
                                                                <tbody class="border-t border-slate-100">
                                                                    <tr>
                                                                        <td class="px-2 pt-1"><input type="number" step="any" class="input input-sm w-24" x-model="r.min" aria-label="Low limit" :class="err(p, 'reference_ranges.' + ri + '.min') && 'input-error'" :aria-invalid="err(p, 'reference_ranges.' + ri + '.min') ? 'true' : null"></td>
                                                                        <td class="px-2 pt-1"><input type="number" step="any" class="input input-sm w-24" x-model="r.max" aria-label="High limit" :class="err(p, 'reference_ranges.' + ri + '.max') && 'input-error'" :aria-invalid="err(p, 'reference_ranges.' + ri + '.max') ? 'true' : null"></td>
                                                                        <td class="px-2 pt-1"><select class="input input-sm w-24" x-model="r.sex" aria-label="Sex"><option value="any">Any</option><option value="male">Male</option><option value="female">Female</option></select></td>
                                                                        <td class="px-2 pt-1"><input type="number" step="any" min="0" class="input input-sm w-20" x-model="r.age_min" aria-label="Minimum age in years" :class="err(p, 'reference_ranges.' + ri + '.age_min') && 'input-error'" :aria-invalid="err(p, 'reference_ranges.' + ri + '.age_min') ? 'true' : null"></td>
                                                                        <td class="px-2 pt-1"><input type="number" step="any" min="0" class="input input-sm w-20" x-model="r.age_max" aria-label="Maximum age in years (exclusive)" :class="err(p, 'reference_ranges.' + ri + '.age_max') && 'input-error'" :aria-invalid="err(p, 'reference_ranges.' + ri + '.age_max') ? 'true' : null"></td>
                                                                        <td class="px-2 pt-1"><input type="text" maxlength="60" class="input input-sm w-28" x-model="r.label" aria-label="Label" placeholder="Optional" :class="err(p, 'reference_ranges.' + ri + '.label') && 'input-error'"></td>
                                                                        <td class="px-1 pt-1"><button type="button" class="icon-btn text-red-500" @click="removeRange(p, ri)" aria-label="Remove interval"><x-icon name="x" /></button></td>
                                                                    </tr>
                                                                    <tr x-show="rangeErrors(p, ri).length">
                                                                        <td colspan="7" class="px-2 pb-1"><p class="field-error mt-0" data-field-error x-text="rangeErrors(p, ri).join(' · ')"></p></td>
                                                                    </tr>
                                                                    <tr><td colspan="7" class="h-1"></td></tr>
                                                                </tbody>
                                                            </template>
                                                        </table>
                                                    </div>
                                                    <p class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-500">The most specific matching interval is used (sex-specific over “any”, age-bounded over open). Ages are in years; use fractions for infants (e.g. 0.08 ≈ 1 month). Leave one side blank for “≤” / “≥” limits.</p>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <div>
                                                        <label class="label" :for="'pmin-' + p.uid">Min. allowed entry</label>
                                                        <input :id="'pmin-' + p.uid" type="number" step="any" class="input input-sm" x-model="p.min_value" placeholder="No limit"
                                                               :class="err(p, 'min_value') && 'input-error'" :aria-invalid="err(p, 'min_value') ? 'true' : null">
                                                        <p class="field-error" data-field-error x-show="err(p, 'min_value')" x-text="err(p, 'min_value')"></p>
                                                    </div>
                                                    <div>
                                                        <label class="label" :for="'pmax-' + p.uid">Max. allowed entry</label>
                                                        <input :id="'pmax-' + p.uid" type="number" step="any" class="input input-sm" x-model="p.max_value" placeholder="No limit"
                                                               :class="err(p, 'max_value') && 'input-error'" :aria-invalid="err(p, 'max_value') ? 'true' : null">
                                                        <p class="field-error" data-field-error x-show="err(p, 'max_value')" x-text="err(p, 'max_value')"></p>
                                                    </div>
                                                    <div>
                                                        <label class="label" :for="'pf-' + p.uid">Formula (calculated)</label>
                                                        <input :id="'pf-' + p.uid" type="text" class="input input-sm font-mono" x-model="p.formula" maxlength="255" placeholder="e.g. bil_total - bil_direct"
                                                               :class="err(p, 'formula') && 'input-error'" :aria-invalid="err(p, 'formula') ? 'true' : null">
                                                        <p class="field-error" data-field-error x-show="err(p, 'formula')" x-text="err(p, 'formula')"></p>
                                                        <p class="help" x-show="!err(p, 'formula')">Optional. Uses other parameters' keys; the value is calculated, not typed.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="p.type === 'table'">
                                            <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-3">
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <label class="label" :for="'prh-' + p.uid">First column heading</label>
                                                        <input :id="'prh-' + p.uid" type="text" class="input input-sm" x-model="p.row_header" maxlength="60" placeholder="e.g. Antigen used"
                                                               :class="err(p, 'row_header') && 'input-error'">
                                                        <p class="field-error" data-field-error x-show="err(p, 'row_header')" x-text="err(p, 'row_header')"></p>
                                                    </div>
                                                    <label class="flex items-end gap-2 pb-2 text-sm text-slate-700"><input type="checkbox" class="checkbox" x-model="p.allow_add_rows"> Allow extra rows during result entry</label>
                                                </div>
                                                <div>
                                                    <label class="label" :for="'prows-' + p.uid">Predefined rows (one per line)</label>
                                                    <textarea :id="'prows-' + p.uid" rows="4" class="input input-sm" x-model="p.rows"
                                                              :class="err(p, 'rows') && 'input-error'" :aria-invalid="err(p, 'rows') ? 'true' : null"></textarea>
                                                    <p class="field-error" data-field-error x-show="err(p, 'rows')" x-text="err(p, 'rows')"></p>
                                                </div>
                                                <div>
                                                    <div class="mb-1 flex items-center justify-between">
                                                        <p class="label mb-0">Result columns</p>
                                                        <button type="button" class="btn btn-sm btn-ghost" @click="addColumn(p)"><x-icon name="plus" /> Column</button>
                                                    </div>
                                                    <p class="field-error" data-field-error x-show="err(p, 'columns')" x-text="err(p, 'columns')"></p>
                                                    <template x-for="(c, ci) in p.columns" :key="c.uid">
                                                        <div class="mb-2">
                                                            <div class="grid gap-2 sm:grid-cols-[1fr_8rem_1fr_auto]">
                                                                <input type="text" class="input input-sm" x-model="c.label" maxlength="60" placeholder="Column label" aria-label="Column label"
                                                                       :class="err(p, 'columns.' + ci + '.label') && 'input-error'" :aria-invalid="err(p, 'columns.' + ci + '.label') ? 'true' : null">
                                                                <select class="input input-sm" x-model="c.type" aria-label="Column type">
                                                                    @foreach ($columnTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                                                </select>
                                                                <textarea x-show="c.type === 'select'" rows="2" class="input input-sm" x-model="c.options" placeholder="Options, one per line" aria-label="Column options"
                                                                          :class="err(p, 'columns.' + ci + '.options') && 'input-error'" :aria-invalid="err(p, 'columns.' + ci + '.options') ? 'true' : null"></textarea>
                                                                <span x-show="c.type !== 'select'"></span>
                                                                <button type="button" class="icon-btn text-red-500" @click="removeColumn(p, ci)" aria-label="Remove column"><x-icon name="x" /></button>
                                                            </div>
                                                            <p class="field-error" data-field-error x-show="columnError(p, ci)" x-text="columnError(p, ci)"></p>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </li>
                            </template>
                        </ul>

                        <div class="border-t border-slate-100 px-4 py-2.5">
                            <button type="button" class="btn btn-sm btn-ghost" @click="addParameter(section)"><x-icon name="plus" /> Add parameter</button>
                        </div>
                    </div>
                </template>

                <button type="button" class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 py-4 text-sm font-semibold text-slate-500 transition hover:border-brand-400 hover:text-brand-700" @click="addSection()">
                    <x-icon name="plus" /> Add section
                </button>
            </section>
        </div>

        {{-- JSON editor --}}
        <div x-cloak x-show="showJson" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="json-title" @keydown.escape.window="showJson = false">
            <div class="absolute inset-0 bg-navy-950/50" @click="showJson = false"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-pop">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 id="json-title" class="text-base font-semibold text-slate-900">Parameter schema (JSON)</h2>
                        <p class="text-xs text-slate-500">Advanced: edit the schema directly. It is validated again on the server when you save.</p>
                    </div>
                    <button type="button" class="icon-btn" @click="showJson = false" aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="flex-1 overflow-auto p-5">
                    <label for="json-draft" class="sr-only">Schema JSON</label>
                    <textarea id="json-draft" x-model="jsonDraft" rows="22" class="input font-mono text-xs" spellcheck="false" @input.stop @change.stop></textarea>
                    <p x-show="jsonError" class="field-error" x-text="jsonError"></p>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" class="btn btn-secondary" @click="showJson = false">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="applyJson()">Apply JSON</button>
                </div>
            </div>
        </div>

        <div class="sticky bottom-0 z-10 -mx-4 mt-8 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center justify-end gap-3">
                <p x-cloak x-show="errorCount > 0" class="mr-auto flex items-center gap-1.5 text-sm font-medium text-red-700">
                    <x-icon name="alert" /> <span x-text="errorCount + (errorCount === 1 ? ' field needs fixing' : ' fields need fixing')"></span>
                    <button type="button" class="ml-1 underline hover:no-underline" @click="scrollToFirstError()">Show</button>
                </p>
                <a href="{{ $editing ? route('tests.show', $test) : route('tests.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary"><x-icon name="check" /> {{ $editing ? 'Save changes' : 'Create test' }}</button>
            </div>
        </div>
    </form>
</x-app-layout>
