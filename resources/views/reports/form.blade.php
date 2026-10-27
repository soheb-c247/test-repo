@php
    $editing = $report->exists;
    $title = $editing ? ($report->isUnderCorrection() ? 'Correct report '.$report->report_number : 'Edit draft '.$report->report_number) : 'New report';
    $dt = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
    $testOptions = $tests->map(fn ($t) => [
        'id' => $t->id,
        'code' => $t->code,
        'name' => $t->name,
        'category' => $t->category,
        'sample_type' => $t->sample_type,
        'price' => $t->price,
        'active' => $t->is_active,
        'instructions' => trim(($t->preparation_instructions ? 'Preparation: '.$t->preparation_instructions.' ' : '').($t->collection_instructions ? 'Collection: '.$t->collection_instructions : '')),
    ])->values();
    $selected = old('test_ids', $selectedTests);
    if (! $editing && ! $patient && old('patient_id')) {
        // Keep the chosen existing patient after a failed validation round-trip.
        $patient = \App\Models\Patient::query()->find((int) old('patient_id'));
    }
    $patientInitial = $patient ? [
        'id' => $patient->id, 'code' => $patient->patient_code, 'name' => $patient->name,
        'summary' => $patient->age_label.' · '.$patient->sex_label,
    ] : [];
    $steps = $editing
        ? ['Patient & registration', 'Tests', 'Results', 'Review & finalize']
        : ['Patient & registration', 'Tests', 'Results', 'Review & finalize'];
    $currentStep = $editing ? 3 : 1;
@endphp

<x-app-layout :title="$title" :breadcrumbs="['Reports' => route('reports.index'), ($editing ? $report->report_number : 'New report') => null]">
    <x-slot:actions>
        @if ($editing)
            <a href="{{ route('reports.show', $report) }}" class="btn btn-secondary"><x-icon name="eye" /> Preview</a>
        @endif
    </x-slot:actions>

    {{-- Steps --}}
    <ol class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Report workflow">
        @foreach ($steps as $i => $label)
            @php $n = $i + 1; $done = $n < $currentStep; $current = $n === $currentStep; @endphp
            <li @class([
                'flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold',
                'border-brand-200 bg-brand-50 text-brand-800' => $done,
                'border-navy-900 bg-navy-900 text-white' => $current,
                'border-slate-200 bg-white text-slate-500' => ! $done && ! $current,
            ]) @if ($current) aria-current="step" @endif>
                <span @class(['flex h-5 w-5 items-center justify-center rounded-full text-[10px]', 'bg-brand-600 text-white' => $done, 'bg-white text-navy-900' => $current, 'bg-slate-100' => ! $done && ! $current])>
                    @if ($done)<x-icon name="check" class="h-3 w-3" />@else{{ $n }}@endif
                </span>
                {{ $label }}
            </li>
        @endforeach
    </ol>

    @if ($editing && $report->isUnderCorrection())
        <div class="mb-6 flex gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4 text-sm text-violet-900">
            <x-icon name="history" class="mt-0.5 h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">Correction in progress — revision {{ $report->revision }}</p>
                <p class="mt-0.5">Reason: {{ $report->correction_reason }}. The previously released version is preserved and remains available in the revision history.</p>
            </div>
        </div>
    @endif

    @error('lock_version')
        <div class="mb-6 flex items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="flex items-center gap-2"><x-icon name="alert" /> {{ $message }}</span>
            <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-secondary">Reload</a>
        </div>
    @enderror

    <form method="POST" action="{{ $editing ? route('reports.update', $report) : route('reports.store') }}" id="report-form" novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $report->lock_version }}">
        @else
            <input type="hidden" name="form_token" value="{{ old('form_token', $formToken) }}">
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            {{-- Step 1: patient & registration --}}
            <section class="card xl:col-span-2" aria-labelledby="patient-heading"
                     x-data="{ name: @js(old('patient_name', $report->patient_name ?? $patient?->name)), age: @js(old('age_value', $editing ? $report->patient_age_value : $patient?->age_value)), unit: @js(old('age_unit', ($editing ? $report->patient_age_unit : $patient?->age_unit) ?: 'years')), sex: @js(old('sex', ($editing ? $report->patient_sex : $patient?->sex) ?: 'unspecified')), phone: @js(old('phone', $patient?->phone)) }"
                     @patient-selected="name = $event.detail.name; age = $event.detail.age_value; unit = $event.detail.age_unit || 'years'; sex = $event.detail.sex; phone = $event.detail.phone"
                     @patient-cleared="name = ''; age = ''; unit = 'years'; sex = 'unspecified'; phone = ''">
                <div class="card-header">
                    <h2 id="patient-heading" class="card-title flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] text-white">1</span> Patient &amp; registration</h2>
                    @if ($editing && $report->patient)
                        <a href="{{ route('patients.show', $report->patient) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ $report->patient->patient_code }}</a>
                    @endif
                </div>
                <div class="card-body space-y-5">
                    @unless ($editing)
                        <div x-data="patientPicker(@js($patientInitial), @js(route('patients.search')))" class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-4">
                            <input type="hidden" name="patient_id" :value="selected ? selected.id : ''" value="{{ old('patient_id', $patient?->id) }}">

                            <div x-show="!selected" class="relative" @click.outside="close()" @keydown.escape="close()">
                                <label for="patient-search" class="label">Existing patient? Search by name, patient ID, phone or report no.</label>
                                <div class="relative">
                                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                    <input id="patient-search" x-ref="search" type="search" x-model="query" @input="onInput()"
                                           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="pickActive()"
                                           @focus="if (query.trim().length >= 2) open = true"
                                           class="input pl-9 pr-24" placeholder="e.g. Asha, PT-000012, 98765 or ACP-2610-00042" autocomplete="off"
                                           role="combobox" aria-autocomplete="list" :aria-expanded="open.toString()" aria-controls="patient-results"
                                           :aria-activedescendant="active >= 0 ? 'patient-option-' + active : null">
                                    <span x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>
                                </div>

                                <div id="patient-results" x-cloak x-show="open" x-transition.opacity.duration.100ms role="listbox"
                                     class="absolute z-30 mt-1 max-h-80 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-pop">
                                    <template x-if="query.trim().length < 2">
                                        <p class="px-3 py-2 text-sm text-slate-500">Type at least 2 characters…</p>
                                    </template>
                                    <template x-if="query.trim().length >= 2 && loading && !results.length">
                                        <p class="px-3 py-2 text-sm text-slate-500">Searching…</p>
                                    </template>
                                    <template x-for="(p, index) in results" :key="p.id">
                                        <button type="button" role="option" :id="'patient-option-' + index" :data-option="index" :aria-selected="(active === index).toString()"
                                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm"
                                                :class="active === index ? 'bg-brand-50' : 'hover:bg-slate-50'"
                                                @mouseenter="active = index" @mousedown.prevent="choose(p)">
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium text-slate-900" x-text="p.name"></span>
                                                <span class="block truncate text-xs text-slate-500" x-text="p.summary"></span>
                                            </span>
                                            <span class="shrink-0 text-right">
                                                <span class="block font-mono text-[11px] text-slate-500" x-text="p.code"></span>
                                                <span class="block font-mono text-[10px] text-slate-400" x-show="p.last_report" x-text="p.last_report"></span>
                                            </span>
                                        </button>
                                    </template>
                                    <template x-if="searched && !loading && !error && !results.length && query.trim().length >= 2">
                                        <p class="px-3 py-2 text-sm text-slate-500">No matching patients — fill in the details below to register a new patient.</p>
                                    </template>
                                    <template x-if="error">
                                        <p class="px-3 py-2 text-sm text-red-600">Search failed. Check your connection and try again, or enter the details manually.</p>
                                    </template>
                                </div>
                            </div>

                            <div x-cloak x-show="selected" class="flex flex-wrap items-center justify-between gap-3">
                                <p class="text-sm">
                                    <span class="badge badge-final mr-2"><x-icon name="check" class="h-3 w-3" /> Existing patient</span>
                                    <span class="font-semibold text-slate-900" x-text="selected?.name"></span>
                                    <span class="font-mono text-xs text-slate-500" x-text="selected?.code"></span>
                                    <span class="block pt-1 text-xs text-slate-500">Details below were filled in from this patient's record — edit them if anything changed.</span>
                                </p>
                                <button type="button" class="btn btn-sm btn-ghost" @click="clear()">Use a new patient instead</button>
                            </div>
                        </div>
                    @endunless

                    <div class="grid gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="patient_name" class="label">Patient name <span class="text-red-500">*</span></label>
                            <input id="patient_name" name="patient_name" x-model="name" required maxlength="150" @class(['input', 'input-error' => $errors->has('patient_name')]) autocomplete="off">
                            @error('patient_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="age_value" class="label">Age</label>
                            <div class="flex">
                                <input id="age_value" name="age_value" type="number" min="0" max="999" inputmode="numeric" x-model="age" @class(['input rounded-r-none', 'input-error' => $errors->has('age_value')])>
                                <label for="age_unit" class="sr-only">Age unit</label>
                                <select id="age_unit" name="age_unit" x-model="unit" class="input w-28 rounded-l-none border-l-0">
                                    @foreach ($ageUnits as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('age_value')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label for="sex" class="label">Sex <span class="text-red-500">*</span></label>
                            <select id="sex" name="sex" x-model="sex" @class(['input', 'input-error' => $errors->has('sex')])>
                                @foreach ($sexes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="phone" class="label">Phone</label>
                            <input id="phone" name="phone" type="tel" x-model="phone" maxlength="30" @class(['input', 'input-error' => $errors->has('phone')])>
                            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="referring_doctor" class="label">Referred by</label>
                            <input id="referring_doctor" name="referring_doctor" value="{{ old('referring_doctor', $report->referring_doctor) }}" maxlength="150" class="input" placeholder="e.g. Self / doctor's name">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="collected_by" class="label">Sample collected by / at</label>
                            <input id="collected_by" name="collected_by" value="{{ old('collected_by', $report->collected_by) }}" maxlength="150" class="input">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="registered_at" class="label">Registered on <span class="text-red-500">*</span></label>
                            <input id="registered_at" name="registered_at" type="datetime-local" value="{{ old('registered_at', $dt($report->registered_at)) }}" required @class(['input', 'input-error' => $errors->has('registered_at')])>
                            @error('registered_at')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="collected_at" class="label">Sample collected on</label>
                            <input id="collected_at" name="collected_at" type="datetime-local" value="{{ old('collected_at', $dt($report->collected_at)) }}" @class(['input', 'input-error' => $errors->has('collected_at')])>
                            @error('collected_at')<p class="field-error">{{ $message }}</p>@else<p class="help">Required before finalizing.</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="sample_id" class="label">Sample ID</label>
                            <input id="sample_id" name="sample_id" value="{{ old('sample_id', $report->sample_id) }}" maxlength="60" class="input font-mono" placeholder="Optional">
                        </div>
                        <div class="sm:col-span-6">
                            <label for="clinical_notes" class="label">Clinical notes</label>
                            <textarea id="clinical_notes" name="clinical_notes" rows="2" maxlength="2000" class="input" placeholder="Optional — printed on the report">{{ old('clinical_notes', $report->clinical_notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Step 2: tests --}}
            <section class="card flex flex-col" aria-labelledby="tests-heading" x-data="testPicker(@js($testOptions), @js(array_map('intval', (array) $selected)))">
                <div class="card-header">
                    <h2 id="tests-heading" class="card-title flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] text-white">2</span> Tests</h2>
                    <span class="text-xs font-semibold text-slate-500" x-text="selected.length + ' selected'"></span>
                </div>
                <template x-for="id in selected" :key="id"><input type="hidden" name="test_ids[]" :value="id"></template>

                <div class="space-y-3 border-b border-slate-100 p-4">
                    <div class="flex flex-wrap gap-1.5" x-show="chosen.length">
                        <template x-for="t in chosen" :key="t.id">
                            <span class="inline-flex items-center gap-1 rounded-full bg-navy-50 py-1 pl-2.5 pr-1 text-xs font-medium text-navy-800">
                                <span x-text="t.name"></span>
                                <button type="button" class="rounded-full p-0.5 hover:bg-navy-100" @click="remove(t.id)" :aria-label="'Remove ' + t.name"><x-icon name="x" class="h-3 w-3" /></button>
                            </span>
                        </template>
                    </div>
                    <p x-show="!chosen.length" class="text-sm text-slate-500">No tests selected yet.</p>
                    @error('test_ids')<p class="field-error">{{ $message }}</p>@enderror
                    <p x-show="total > 0" class="text-xs text-slate-500">Listed price total: <strong class="text-slate-800">{{ config('lab.currency') }}<span x-text="total.toFixed(2)"></span></strong></p>
                    @if ($editing)
                        <p class="help">Changing the selection updates the result fields after you save. Tests already on this report keep the definition version they were added with.</p>
                    @endif
                </div>

                <div class="p-4 pb-2">
                    <label for="test-filter" class="sr-only">Filter tests</label>
                    <input id="test-filter" type="search" x-model="filter" class="input input-sm" placeholder="Filter {{ $tests->count() }} tests by name, code or category">
                </div>
                <div class="max-h-[26rem] flex-1 overflow-y-auto px-2 pb-3">
                    <template x-for="[category, items] in groups" :key="category">
                        <fieldset class="mb-2">
                            <legend class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" x-text="category"></legend>
                            <template x-for="t in items" :key="t.id">
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50" :class="isSelected(t.id) && 'bg-brand-50/60'">
                                    <input type="checkbox" class="checkbox mt-0.5" :checked="isSelected(t.id)" @change="toggle(t.id)">
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="truncate text-sm font-medium text-slate-800" x-text="t.name"></span>
                                            <span class="font-mono text-[10px] text-slate-400" x-text="t.code"></span>
                                        </span>
                                        <span class="block text-xs text-slate-500">
                                            <span x-text="t.sample_type || 'Sample not specified'"></span>
                                            <template x-if="t.price"><span> · {{ config('lab.currency') }}<span x-text="Number(t.price).toFixed(2)"></span></span></template>
                                            <template x-if="!t.active"><span class="text-amber-700"> · inactive</span></template>
                                        </span>
                                        <span x-show="isSelected(t.id) && t.instructions" class="mt-0.5 block text-xs text-brand-800" x-text="t.instructions"></span>
                                    </span>
                                </label>
                            </template>
                        </fieldset>
                    </template>
                    <p x-show="!filtered.length" class="px-2 py-6 text-center text-sm text-slate-500">No tests match “<span x-text="filter"></span>”.</p>
                </div>
            </section>
        </div>

        {{-- Step 3: results --}}
        <section id="results" class="mt-6 scroll-mt-20" aria-labelledby="results-heading">
            <h2 id="results-heading" class="mb-3 flex items-center gap-2 text-base font-semibold text-navy-900">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] text-white">3</span> Results
            </h2>

            @if (! $editing)
                <div class="card">
                    <x-empty-state icon="beaker" title="Results are entered after the draft is created"
                                   message="Fill in the patient details, choose the tests and select “Save & enter results”. The result form is generated from each test definition." />
                </div>
            @elseif ($report->items->isEmpty())
                <div class="card"><x-empty-state icon="flask" title="No tests on this report" message="Select at least one test above and save the draft." /></div>
            @else
                <div class="space-y-6">
                    @foreach ($report->items as $item)
                        @include('reports.partials.result-item', ['item' => $item, 'report' => $report, 'evaluator' => $evaluator, 'ageYears' => $ageYears])
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Action bar --}}
        <div class="sticky bottom-0 z-10 -mx-4 mt-8 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <a href="{{ $editing ? route('reports.show', $report) : route('reports.index') }}" class="btn btn-ghost">Cancel</a>
                    @if ($editing && $report->revision === 0)
                        <button type="submit" form="delete-draft" class="btn btn-danger-ghost"><x-icon name="trash" /> Discard draft</button>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($editing)
                        <button type="submit" name="intent" value="save" class="btn btn-secondary"><x-icon name="check" /> Save draft</button>
                        <button type="submit" name="intent" value="review" class="btn btn-primary">Save &amp; review <x-icon name="chevron-right" /></button>
                    @else
                        <button type="submit" name="intent" value="continue" class="btn btn-primary">Save &amp; enter results <x-icon name="chevron-right" /></button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    @if ($editing && $report->revision === 0)
        <form id="delete-draft" method="POST" action="{{ route('reports.destroy', $report) }}"
              data-confirm="Discard draft {{ $report->report_number }}? Entered results will be removed." data-confirm-title="Discard draft" data-confirm-label="Discard draft" data-confirm-danger="1">
            @csrf
            @method('DELETE')
        </form>
    @endif
</x-app-layout>
