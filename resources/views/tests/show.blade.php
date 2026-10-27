<x-app-layout :title="$test->name" :breadcrumbs="['Tests' => route('tests.index'), $test->code => null]">
    <x-slot:actions>
        @if ($test->trashed())
            <span class="badge badge-neutral"><x-icon name="archive" class="h-3 w-3" /> Archived</span>
            <form method="POST" action="{{ route('tests.restore', $test) }}">
                @csrf
                <button type="submit" class="btn btn-secondary"><x-icon name="restore" /> Restore</button>
            </form>
        @else
            <span @class(['badge', 'badge-final' => $test->is_active, 'badge-neutral' => ! $test->is_active])>{{ $test->is_active ? 'Active' : 'Inactive' }}</span>
            <form method="POST" action="{{ route('tests.status', $test) }}"
                  @if ($test->is_active) data-confirm="Deactivate {{ $test->code }}? It will no longer be offered for new reports." data-confirm-title="Deactivate test" data-confirm-label="Deactivate" @endif>
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-secondary"><x-icon :name="$test->is_active ? 'eye-off' : 'check-circle'" /> {{ $test->is_active ? 'Deactivate' : 'Activate' }}</button>
            </form>
            <form method="POST" action="{{ route('tests.duplicate', $test) }}">
                @csrf
                <button type="submit" class="btn btn-secondary"><x-icon name="copy" /> Duplicate</button>
            </form>
            <a href="{{ route('tests.edit', $test) }}" class="btn btn-primary"><x-icon name="edit" /> Edit</a>
        @endif
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($test->needs_review || $test->review_notes)
                <div @class(['rounded-xl border p-4 text-sm', 'border-amber-200 bg-amber-50 text-amber-900' => $test->needs_review, 'border-slate-200 bg-white text-slate-600' => ! $test->needs_review])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex gap-2">
                            <x-icon name="flag" class="mt-0.5 h-4 w-4 shrink-0" />
                            <div>
                                <p class="font-semibold">{{ $test->needs_review ? 'Needs review before clinical use' : 'Import notes' }}</p>
                                <p class="mt-1">{{ $test->review_notes }}</p>
                            </div>
                        </div>
                        @if ($test->needs_review && ! $test->trashed())
                            <form method="POST" action="{{ route('tests.reviewed', $test) }}"
                                  data-confirm="Confirm that the parameters, units and reference intervals of {{ $test->code }} have been reviewed for use in this laboratory." data-confirm-title="Mark as reviewed" data-confirm-label="Mark reviewed">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-secondary"><x-icon name="check" /> Mark reviewed</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <section class="card" aria-labelledby="params-title">
                <div class="card-header">
                    <h2 id="params-title" class="card-title">Parameters</h2>
                    <span class="text-xs text-slate-500">Version {{ $test->version }} · {{ $test->parameter_count }} {{ Str::plural('parameter', $test->parameter_count) }}</span>
                </div>
                <div class="relative overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr><th>Parameter</th><th>Type</th><th>Unit</th><th>Reference interval</th><th class="hidden lg:table-cell">Key</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($test->schema['sections'] ?? [] as $section)
                                @if ($section['title'] !== '')
                                    <tr class="bg-slate-50/70 hover:bg-slate-50/70"><td colspan="5" class="py-2 text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $section['title'] }}</td></tr>
                                @endif
                                @foreach ($section['parameters'] as $p)
                                    <tr>
                                        <td>
                                            <span class="font-medium text-slate-900">{{ $p['label'] }}</span>
                                            @if ($p['required'])<span class="text-red-500" title="Required">*</span>@endif
                                            @if (! ($p['show_in_pdf'] ?? true))<span class="badge badge-neutral ml-1">Hidden on report</span>@endif
                                            @if ($p['method'])<span class="block text-xs text-slate-500">{{ $p['method'] }}</span>@endif
                                            @if (! empty($p['formula']))<span class="block font-mono text-[11px] text-brand-700">= {{ $p['formula'] }}</span>@endif
                                        </td>
                                        <td class="text-xs text-slate-600">{{ \Illuminate\Support\Str::before($types[$p['type']] ?? $p['type'], ' (') }}
                                            @if (! empty($p['options']))<span class="block text-slate-400">{{ implode(' / ', array_slice($p['options'], 0, 4)) }}{{ count($p['options']) > 4 ? ' …' : '' }}</span>@endif
                                            @if ($p['type'] === 'table')<span class="block text-slate-400">{{ count($p['rows']) }} rows × {{ count($p['columns']) }} columns</span>@endif
                                        </td>
                                        <td class="text-xs">{{ $p['unit'] ?: '—' }}</td>
                                        <td class="text-xs">{{ $evaluator->referenceLabel($p, null, null, false) ?: '—' }}</td>
                                        <td class="hidden font-mono text-[11px] text-slate-400 lg:table-cell">{{ $p['key'] }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($test->reporting_notes)
                <section class="card" aria-labelledby="notes-title">
                    <div class="card-header"><h2 id="notes-title" class="card-title">Reporting notes</h2></div>
                    <div class="card-body prose-notes text-sm text-slate-700 [&_p]:mb-2 [&_table]:my-2 [&_td]:border [&_td]:border-slate-200 [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:px-2 [&_th]:py-1 [&_th]:text-left">
                        {!! \App\Support\NotesFormatter::toHtml($test->reporting_notes) !!}
                    </div>
                </section>
            @endif
        </div>

        <aside class="space-y-6">
            <section class="card" aria-labelledby="details-title">
                <div class="card-header"><h2 id="details-title" class="card-title">Details</h2></div>
                <dl class="card-body grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-slate-500">Code</dt><dd class="font-mono font-semibold">{{ $test->code }}</dd>
                    <dt class="text-slate-500">Category</dt><dd>{{ $test->category }}</dd>
                    <dt class="text-slate-500">Specimen</dt><dd>{{ $test->sample_type ?: '—' }}</dd>
                    <dt class="text-slate-500">Method</dt><dd>{{ $test->method ?: '—' }}</dd>
                    <dt class="text-slate-500">Price</dt><dd>{{ $test->price !== null ? config('lab.currency').number_format((float) $test->price, 2) : '—' }}</dd>
                    <dt class="text-slate-500">Turnaround</dt><dd>{{ $test->turnaround_hours ? $test->turnaround_hours.' h' : '—' }}</dd>
                    <dt class="text-slate-500">Used in</dt><dd>{{ $usage }} report {{ Str::plural('item', $usage) }}</dd>
                    <dt class="text-slate-500">Source</dt><dd>{{ $test->source === 'workbook' ? 'Reference workbook import' : 'Created in app' }}</dd>
                    @if ($test->description)<dt class="text-slate-500">About</dt><dd>{{ $test->description }}</dd>@endif
                    @if ($test->preparation_instructions)<dt class="text-slate-500">Preparation</dt><dd>{{ $test->preparation_instructions }}</dd>@endif
                    @if ($test->collection_instructions)<dt class="text-slate-500">Collection</dt><dd>{{ $test->collection_instructions }}</dd>@endif
                </dl>
            </section>

            <section class="card" aria-labelledby="versions-title">
                <div class="card-header"><h2 id="versions-title" class="card-title">Version history</h2></div>
                <ol class="card-body space-y-3 text-sm">
                    @forelse ($versions as $version)
                        <li class="flex items-start gap-3">
                            <span @class(['mt-0.5 flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-[11px] font-bold', 'bg-navy-900 text-white' => $version->version === $test->version, 'bg-slate-100 text-slate-600' => $version->version !== $test->version])>v{{ $version->version }}</span>
                            <div>
                                <p class="font-medium text-slate-800">{{ $version->version === $test->version ? 'Current version' : 'Previous version' }}</p>
                                <p class="text-xs text-slate-500">{{ $version->created_at?->format('d M Y, H:i') }}{{ $version->author ? ' · '.$version->author->name : ' · system import' }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-slate-500">No versions recorded.</li>
                    @endforelse
                </ol>
            </section>

            @unless ($test->trashed())
                <form method="POST" action="{{ route('tests.destroy', $test) }}"
                      data-confirm="Archive {{ $test->code }}? It is hidden from the catalog and cannot be added to new reports. Existing reports keep their copy and can be restored later."
                      data-confirm-title="Archive test" data-confirm-label="Archive" data-confirm-danger="1">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger-ghost w-full"><x-icon name="archive" /> Archive test</button>
                </form>
            @endunless
        </aside>
    </div>
</x-app-layout>
