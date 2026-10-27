@php
    // $target: CSS selector of the results block that live search refreshes.
    $fragment = $fragment ?? null;
    $keep = $keep ?? [];
    $active = collect($filters)->only(['q', 'status', 'from', 'to', 'category'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()
        || ! empty($filters['sort']);
    $sortOptions = [
        'created' => 'Date created',
        'registered' => 'Registration date',
        'collected' => 'Collection date',
        'reported' => 'Reported date',
        'report_number' => 'Report no.',
        'patient' => 'Patient name',
        'status' => 'Status',
    ];
@endphp
<form method="GET" action="{{ $action }}{{ $fragment ? '#'.$fragment : '' }}" role="search"
      x-data="liveFilters" data-target="{{ $target }}"
      class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
    @foreach ($keep as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <div class="w-full min-w-[14rem] sm:w-auto sm:flex-1">
        <label for="f-q-{{ $fragment }}" class="label">Search</label>
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input id="f-q-{{ $fragment }}" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="input input-sm pl-9 pr-24"
                   placeholder="Report no., patient or test" maxlength="100" autocomplete="off"
                   @input="queue()" @keydown.enter.prevent="search()">
            <span x-cloak x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>
        </div>
    </div>

    <div class="contents">
        <div class="w-full sm:w-40">
            <label for="f-status-{{ $fragment }}" class="label">Status</label>
            <select id="f-status-{{ $fragment }}" name="status" class="input input-sm">
                <option value="">All statuses</option>
                @foreach ($statusFilters as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($showCategory ?? true)
            <div class="w-full sm:w-44">
                <label for="f-cat-{{ $fragment }}" class="label">Category</label>
                <select id="f-cat-{{ $fragment }}" name="category" class="input input-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="w-full sm:w-36">
            <label for="f-from-{{ $fragment }}" class="label">Registered from</label>
            <input id="f-from-{{ $fragment }}" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="input input-sm">
        </div>
        <div class="w-full sm:w-36">
            <label for="f-to-{{ $fragment }}" class="label">Registered to</label>
            <input id="f-to-{{ $fragment }}" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="input input-sm">
        </div>
        <x-sort-controls :options="$sortOptions" default="created" default-dir="desc" :id-prefix="'f-sort-'.$fragment" class="w-full sm:w-72" />
        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm h-[38px] px-4"><x-icon name="filter" /> Apply</button>
            @if ($active)
                <a href="{{ $action }}{{ $keep ? '?'.http_build_query($keep) : '' }}{{ $fragment ? '#'.$fragment : '' }}" class="btn btn-ghost btn-sm h-[38px]">Reset</a>
            @endif
        </div>
    </div>
</form>
