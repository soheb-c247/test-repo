@php
    $ready = $report->isDraft() && empty($missing) && $report->collected_at && $report->items->isNotEmpty();
    $flagged = [];
    foreach ($report->items as $item) {
        foreach ($item->sections() as $section) {
            foreach ($section['parameters'] as $parameter) {
                $flag = $item->flags[$parameter['key']] ?? null;
                if (in_array($flag, ['high', 'low'], true)) {
                    $flagged[] = [
                        'test' => $item->test_name,
                        'label' => $parameter['label'],
                        'value' => $evaluator->formatValue($parameter, $item->results[$parameter['key']] ?? null),
                        'unit' => $parameter['unit'],
                        'flag' => $flag,
                    ];
                }
            }
        }
    }
@endphp

<x-app-layout :title="$report->report_number" :breadcrumbs="['Reports' => route('reports.index'), $report->report_number => null]">
    <x-slot:actions>
        <x-report-status :report="$report" />
        @if ($report->isDraft())
            <a href="{{ route('reports.edit', $report) }}" class="btn btn-secondary"><x-icon name="edit" /> Edit</a>
        @endif
        <a href="{{ route('reports.pdf', [$report, 'inline' => 1]) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" /> Print</a>
        <a href="{{ route('reports.pdf', $report) }}" class="btn btn-secondary"><x-icon name="download" /> {{ $report->isDraft() ? 'Draft PDF' : 'Download PDF' }}</a>
        @if ($report->isDraft())
            <form method="POST" action="{{ route('reports.finalize', $report) }}"
                  data-confirm="Finalize and release {{ $report->report_number }}? The report becomes read-only; later changes require a documented correction."
                  data-confirm-title="Finalize report" data-confirm-label="Finalize &amp; release">
                @csrf
                <input type="hidden" name="lock_version" value="{{ $report->lock_version }}">
                <button type="submit" class="btn btn-accent" @disabled(! $ready)><x-icon name="check-circle" /> Finalize</button>
            </form>
        @endif
    </x-slot:actions>

    @if ($report->is_demo)
        <div class="mb-4 rounded-lg border border-slate-200 bg-slate-100 px-4 py-2 text-xs text-slate-600">
            <strong>Demo data.</strong> This synthetic report was created by the demo seeder and contains no real patient information.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- Preview --}}
        <section class="card overflow-hidden" aria-label="Report preview">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Report preview</h2>
                    <p class="text-xs text-slate-500">Rendered from this report's stored snapshot — the PDF uses the same layout.</p>
                </div>
                <a href="{{ route('reports.preview', $report) }}" target="_blank" rel="noopener" class="btn btn-sm btn-ghost"><x-icon name="eye" /> Open in new tab</a>
            </div>
            <div class="bg-slate-100 p-3 sm:p-6">
                <iframe src="{{ route('reports.preview', $report) }}" title="Preview of report {{ $report->report_number }}"
                        class="mx-auto block h-[1100px] w-full max-w-[820px] rounded-md bg-white shadow-card ring-1 ring-slate-200" loading="lazy"></iframe>
            </div>
        </section>

        <aside class="space-y-6">
            @if ($report->isDraft())
                <section class="card" aria-labelledby="checklist-title">
                    <div class="card-header"><h2 id="checklist-title" class="card-title">Ready to finalize?</h2></div>
                    <ul class="card-body space-y-2 text-sm">
                        <li class="flex items-start gap-2">
                            <x-icon :name="$report->items->isNotEmpty() ? 'check-circle' : 'alert'" :class="\Illuminate\Support\Arr::toCssClasses(['mt-0.5 h-4 w-4 shrink-0', 'text-brand-600' => $report->items->isNotEmpty(), 'text-amber-600' => $report->items->isEmpty()])" />
                            {{ $report->items->count() }} {{ Str::plural('test', $report->items->count()) }} on the report
                        </li>
                        <li class="flex items-start gap-2">
                            <x-icon :name="$report->collected_at ? 'check-circle' : 'alert'" :class="\Illuminate\Support\Arr::toCssClasses(['mt-0.5 h-4 w-4 shrink-0', 'text-brand-600' => $report->collected_at, 'text-amber-600' => ! $report->collected_at])" />
                            {{ $report->collected_at ? 'Sample collection time recorded' : 'Sample collection time is missing' }}
                        </li>
                        <li class="flex items-start gap-2">
                            <x-icon :name="empty($missing) ? 'check-circle' : 'alert'" :class="\Illuminate\Support\Arr::toCssClasses(['mt-0.5 h-4 w-4 shrink-0', 'text-brand-600' => empty($missing), 'text-amber-600' => ! empty($missing)])" />
                            @if (empty($missing))
                                All required results entered
                            @else
                                <span>{{ count($missing) }} required {{ Str::plural('result', count($missing)) }} missing
                                    <span class="mt-1 block text-xs text-slate-500">{{ implode(' · ', array_slice($missing, 0, 6)) }}{{ count($missing) > 6 ? ' …' : '' }}</span>
                                </span>
                            @endif
                        </li>
                    </ul>
                    <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                        @if ($ready)
                            Opening the preview or PDF does not finalize the report — use <strong>Finalize</strong> when the results are verified.
                        @else
                            <a href="{{ route('reports.edit', $report) }}#results" class="font-semibold text-brand-700 hover:underline">Complete the results</a> to enable finalization.
                        @endif
                    </div>
                </section>
            @endif

            <section class="card" aria-labelledby="summary-title">
                <div class="card-header"><h2 id="summary-title" class="card-title">Summary</h2></div>
                <dl class="card-body grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-slate-500">Patient</dt>
                    <dd class="font-medium text-slate-900">
                        @if ($report->patient)
                            <a href="{{ route('patients.show', $report->patient) }}" class="hover:underline">{{ $report->patient_name }}</a>
                        @else
                            {{ $report->patient_name }}
                        @endif
                    </dd>
                    <dt class="text-slate-500">Age / Sex</dt><dd>{{ $report->patient_age_label }} · {{ $report->patient_sex_label }}</dd>
                    <dt class="text-slate-500">Referred by</dt><dd>{{ $report->referring_doctor ?: '—' }}</dd>
                    <dt class="text-slate-500">Registered</dt><dd>{{ $report->registered_at?->format('d M Y, H:i') }}</dd>
                    <dt class="text-slate-500">Collected</dt><dd>{{ $report->collected_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    <dt class="text-slate-500">Reported</dt><dd>{{ $report->isFinal() ? $report->reported_at?->format('d M Y, H:i') : 'Not yet released' }}</dd>
                    @if ($report->isFinal())
                        <dt class="text-slate-500">Finalized by</dt><dd>{{ $report->finalizer?->name ?? '—' }}</dd>
                    @endif
                    <dt class="text-slate-500">Tests</dt>
                    <dd>
                        <ul class="space-y-0.5">
                            @foreach ($report->items as $item)
                                <li>{{ $item->test_name }} <span class="font-mono text-[11px] text-slate-400">v{{ $item->definition_version }}</span></li>
                            @endforeach
                        </ul>
                    </dd>
                </dl>
            </section>

            <section class="card" aria-labelledby="billing-title">
                <div class="card-header">
                    <h2 id="billing-title" class="card-title">Billing</h2>
                    @if ($bill)<x-bill-status :bill="$bill" />@endif
                </div>
                <div class="card-body text-sm">
                    @if ($bill)
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
                            <dt class="text-slate-500">Bill no.</dt><dd><a href="{{ route('billing.show', $bill) }}" class="font-mono text-xs font-semibold text-navy-800 hover:underline">{{ $bill->bill_number }}</a></dd>
                            <dt class="text-slate-500">Total</dt><dd class="tabular-nums">{{ config('lab.currency') }}{{ number_format((float) $bill->total, 2) }}</dd>
                            <dt class="text-slate-500">Balance due</dt><dd @class(['tabular-nums font-semibold', 'text-red-700' => (float) $bill->balance > 0, 'text-brand-700' => (float) $bill->balance <= 0])>{{ config('lab.currency') }}{{ number_format((float) $bill->balance, 2) }}</dd>
                        </dl>
                        <a href="{{ route('billing.show', $bill) }}" class="btn btn-secondary mt-4 w-full justify-center"><x-icon name="receipt" /> {{ (float) $bill->balance > 0 ? 'Open bill & record payment' : 'Open bill' }}</a>
                    @else
                        <p class="text-slate-500">This report has not been billed yet.</p>
                        @if ($report->items->isNotEmpty())
                            <a href="{{ route('billing.create', ['report' => $report->id]) }}" class="btn btn-accent mt-4 w-full justify-center"><x-icon name="receipt" /> Create bill</a>
                        @endif
                    @endif
                </div>
            </section>

            <section class="card" aria-labelledby="flags-title">
                <div class="card-header">
                    <h2 id="flags-title" class="card-title">Flagged results</h2>
                    <span class="badge {{ $flagged ? 'badge-danger' : 'badge-neutral' }}">{{ count($flagged) }}</span>
                </div>
                <div class="card-body text-sm">
                    @forelse ($flagged as $row)
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 py-1.5 last:border-0">
                            <span><span class="font-medium text-slate-800">{{ $row['label'] }}</span><span class="block text-xs text-slate-500">{{ $row['test'] }}</span></span>
                            <span class="whitespace-nowrap {{ $row['flag'] === 'high' ? 'flag-high' : 'flag-low' }}">{{ $row['value'] }} {{ $row['unit'] }} {{ $row['flag'] === 'high' ? '↑' : '↓' }}</span>
                        </div>
                    @empty
                        <p class="text-slate-500">No numeric result is outside its configured reference interval.</p>
                    @endforelse
                    <p class="mt-3 text-xs text-slate-400">Flags compare numeric results with the configured interval for the patient's sex/age. They are informational, not a diagnosis.</p>
                </div>
            </section>

            <section class="card" aria-labelledby="actions-title" x-data="{ correcting: {{ $errors->has('reason') ? 'true' : 'false' }} }">
                <div class="card-header"><h2 id="actions-title" class="card-title">More actions</h2></div>
                <div class="card-body space-y-2">
                    @if ($report->isFinal())
                        <button type="button" class="btn btn-secondary w-full justify-start" @click="correcting = true"><x-icon name="edit" /> Correct report…</button>
                    @endif
                    <form method="POST" action="{{ route('reports.repeat', $report) }}"
                          data-confirm="Create a new draft for {{ $report->patient_name }} with the same tests? Results are not copied." data-confirm-title="Repeat tests" data-confirm-label="Create draft">
                        @csrf
                        <button type="submit" class="btn btn-secondary w-full justify-start"><x-icon name="repeat" /> Repeat tests as new report</button>
                    </form>
                    @if ($report->isDraft() && $report->revision === 0)
                        <form method="POST" action="{{ route('reports.destroy', $report) }}"
                              data-confirm="Discard draft {{ $report->report_number }}?" data-confirm-title="Discard draft" data-confirm-label="Discard" data-confirm-danger="1">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger-ghost w-full justify-start"><x-icon name="trash" /> Discard draft</button>
                        </form>
                    @endif
                </div>

                {{-- Correction dialog --}}
                @if ($report->isFinal())
                    <div x-cloak x-show="correcting" class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="correct-title" @keydown.escape.window="correcting = false">
                        <div class="absolute inset-0 bg-navy-950/50" @click="correcting = false"></div>
                        <form method="POST" action="{{ route('reports.correction', $report) }}" class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-pop"
                              x-effect="if (correcting) $nextTick(() => $refs.reason.focus())">
                            @csrf
                            <h2 id="correct-title" class="text-base font-semibold text-slate-900">Correct {{ $report->report_number }}</h2>
                            <p class="mt-1 text-sm text-slate-600">The released version (revision {{ $report->revision }}) is saved permanently in the revision history. The report then reopens as a draft for revision {{ $report->revision + 1 }} and must be finalized again.</p>
                            <label for="reason" class="label mt-4">Reason for correction <span class="text-red-500">*</span></label>
                            <textarea id="reason" name="reason" x-ref="reason" rows="3" minlength="5" maxlength="500" required @class(['input', 'input-error' => $errors->has('reason')])>{{ old('reason') }}</textarea>
                            @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" class="btn btn-secondary" @click="correcting = false">Cancel</button>
                                <button type="submit" class="btn btn-primary">Start correction</button>
                            </div>
                        </form>
                    </div>
                @endif
            </section>

            <section class="card" aria-labelledby="history-title">
                <div class="card-header"><h2 id="history-title" class="card-title">Revision history</h2></div>
                <div class="card-body">
                    @if ($report->revisions->isEmpty())
                        <p class="text-sm text-slate-500">{{ $report->isFinal() ? 'Original release — no corrections.' : 'Not yet released.' }}</p>
                    @else
                        <ol class="space-y-3 text-sm">
                            @foreach ($report->revisions as $revision)
                                <li class="rounded-lg border border-slate-200 p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-semibold text-slate-800">Revision {{ $revision->revision }} <span class="font-normal text-slate-500">(superseded)</span></span>
                                        <span class="flex gap-1">
                                            <a href="{{ route('reports.revision', [$report, $revision]) }}" target="_blank" rel="noopener" class="icon-btn" title="View revision {{ $revision->revision }}"><x-icon name="eye" /></a>
                                            <a href="{{ route('reports.revision', [$report, $revision, 'format' => 'pdf']) }}" class="icon-btn" title="Download revision {{ $revision->revision }} PDF"><x-icon name="download" /></a>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Superseded {{ $revision->created_at?->format('d M Y, H:i') }}{{ $revision->author ? ' by '.$revision->author->name : '' }}</p>
                                    @if ($revision->reason)<p class="mt-1 text-xs text-slate-700">Reason: {{ $revision->reason }}</p>@endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-app-layout>
