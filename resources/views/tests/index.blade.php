@php
    $status = $filters['status'] ?? '';
    $tabs = [
        '' => ['All', $counts['all']],
        'active' => ['Active', $counts['active']],
        'review' => ['Needs review', $counts['review']],
        'archived' => ['Archived', $counts['archived']],
    ];
@endphp
<x-app-layout title="Tests" :breadcrumbs="['Dashboard' => route('dashboard'), 'Tests' => null]"
              subtitle="Laboratory test definitions: parameters, units, methods and reference intervals. Edits create a new version; existing reports keep theirs.">
    <x-slot:actions>
        <a href="{{ route('tests.create') }}" class="btn btn-accent"><x-icon name="plus" /> New test</a>
    </x-slot:actions>

    <div class="card">
        <div class="flex flex-wrap items-center gap-1 border-b border-slate-100 px-3 pt-2" role="tablist" aria-label="Test status">
            @foreach ($tabs as $key => [$label, $count])
                <a href="{{ route('tests.index', array_filter(['status' => $key, 'q' => $filters['q'] ?? null, 'category' => $filters['category'] ?? null])) }}"
                   role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}"
                   @class(['-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold transition', 'border-navy-900 text-navy-900' => $status === $key, 'border-transparent text-slate-500 hover:text-slate-800' => $status !== $key])>
                    {{ $label }}
                    <span @class(['rounded-full px-1.5 py-0.5 text-[11px]', 'bg-navy-900 text-white' => $status === $key, 'bg-slate-100 text-slate-600' => $status !== $key])>{{ $count }}</span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('tests.index') }}" x-data="liveFilters" data-target="#test-results"
              class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4" role="search">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <div class="w-full min-w-[14rem] sm:w-auto sm:flex-1">
                <label for="tq" class="label">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input id="tq" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="input input-sm pl-9 pr-24"
                           placeholder="Search by code, name or category" maxlength="100" autocomplete="off"
                           @input="queue()" @keydown.enter.prevent="search()">
                    <span x-cloak x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>
                </div>
            </div>
            <div class="contents">
                <div class="w-full sm:w-52">
                    <label for="tc" class="label">Category</label>
                    <select id="tc" name="category" class="input input-sm">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <x-sort-controls :options="['name' => 'Test name', 'code' => 'Code', 'category' => 'Category', 'updated' => 'Last updated']"
                                 default="name" default-dir="asc" id-prefix="ts" class="w-full sm:w-72" />
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm h-[38px] px-4"><x-icon name="filter" /> Apply</button>
                    @if (! empty($filters['q']) || ! empty($filters['category']) || ! empty($filters['sort']))
                        <a href="{{ route('tests.index', array_filter(['status' => $status])) }}" class="btn btn-ghost btn-sm h-[38px]">Reset</a>
                    @endif
                </div>
            </div>
        </form>

        <div id="test-results">
        @if ($tests->isEmpty())
            @if (! empty($filters['q']) || ! empty($filters['category']) || $status)
                <x-empty-state icon="search" title="No tests match" message="Try another search term, category or tab." />
            @else
                <x-empty-state icon="flask" title="No test definitions yet" message="Create a test or import the reference catalog with: php artisan lab:import-reference-tests">
                    <a href="{{ route('tests.create') }}" class="btn btn-accent"><x-icon name="plus" /> New test</a>
                </x-empty-state>
            @endif
        @else
            <div class="relative overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col"><x-sort-link column="code" label="Code" default="name" default-dir="asc" /></th>
                            <th scope="col"><x-sort-link column="name" label="Test name" default="name" default-dir="asc" /></th>
                            <th scope="col" class="hidden md:table-cell"><x-sort-link column="category" label="Category" default="name" default-dir="asc" /></th>
                            <th scope="col" class="hidden lg:table-cell">Sample</th>
                            <th scope="col" class="text-right">Params</th>
                            <th scope="col" class="hidden text-right xl:table-cell">Price</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="hidden lg:table-cell"><x-sort-link column="updated" label="Updated" default="name" default-dir="asc" /></th>
                            <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tests as $test)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-xs font-semibold text-navy-800">{{ $test->code }}</td>
                                <td class="min-w-[14rem]">
                                    <a href="{{ route('tests.show', $test) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $test->name }}</a>
                                    <div class="mt-0.5 flex flex-wrap gap-1">
                                        <span class="text-xs text-slate-500">v{{ $test->version }}</span>
                                        @if ($test->needs_review)<span class="badge badge-warn"><x-icon name="flag" class="h-3 w-3" /> Review</span>@endif
                                        @if ($test->report_items_count)<span class="text-xs text-slate-400">· used in {{ $test->report_items_count }} {{ Str::plural('report', $test->report_items_count) }}</span>@endif
                                    </div>
                                </td>
                                <td class="hidden md:table-cell">{{ $test->category }}</td>
                                <td class="hidden text-xs text-slate-600 lg:table-cell">{{ $test->sample_type ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ $test->parameter_count }}</td>
                                <td class="hidden text-right tabular-nums xl:table-cell">{{ $test->price !== null ? config('lab.currency').number_format((float) $test->price, 2) : '—' }}</td>
                                <td>
                                    @if ($test->trashed())
                                        <span class="badge badge-neutral"><x-icon name="archive" class="h-3 w-3" /> Archived</span>
                                    @elseif ($test->is_active)
                                        <span class="badge badge-final">Active</span>
                                    @else
                                        <span class="badge badge-neutral">Inactive</span>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap text-xs text-slate-500 lg:table-cell">{{ $test->updated_at?->format('d M Y') }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-0.5">
                                        <a href="{{ route('tests.show', $test) }}" class="icon-btn" title="View" aria-label="View {{ $test->code }}"><x-icon name="eye" /></a>
                                        @unless ($test->trashed())
                                            <a href="{{ route('tests.edit', $test) }}" class="icon-btn" title="Edit" aria-label="Edit {{ $test->code }}"><x-icon name="edit" /></a>
                                            <form method="POST" action="{{ route('tests.duplicate', $test) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="icon-btn" title="Duplicate" aria-label="Duplicate {{ $test->code }}"><x-icon name="copy" /></button>
                                            </form>
                                            <form method="POST" action="{{ route('tests.status', $test) }}" class="inline"
                                                  @if ($test->is_active) data-confirm="Deactivate {{ $test->code }}? It will no longer be offered for new reports. Existing reports are unaffected." data-confirm-title="Deactivate test" data-confirm-label="Deactivate" @endif>
                                                @csrf @method('PATCH')
                                                <button type="submit" class="icon-btn" title="{{ $test->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $test->is_active ? 'Deactivate' : 'Activate' }} {{ $test->code }}">
                                                    <x-icon :name="$test->is_active ? 'eye-off' : 'check-circle'" />
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('tests.restore', $test) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="icon-btn" title="Restore" aria-label="Restore {{ $test->code }}"><x-icon name="restore" /></button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $tests->links() }}
        @endif
        </div>
    </div>
</x-app-layout>
