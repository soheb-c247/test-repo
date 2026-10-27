{{--
    Column heading that shows the active sort. Sorting itself is chosen with
    the "Sort by" / "Order" controls in the filter bar and applied with Apply.
--}}
@props(['column', 'label', 'default' => null, 'defaultDir' => 'desc', 'fragment' => null])
@php
    $current = request('sort', $default);
    $active = $current === $column;
    $dir = request('dir', $current === $default ? $defaultDir : 'asc') === 'asc' ? 'asc' : 'desc';
@endphp
<span @class(['inline-flex items-center gap-1', 'text-navy-900' => $active])
      @if ($active) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }}
    @if ($active)
        <x-icon :name="$dir === 'asc' ? 'arrow-up' : 'arrow-down'" class="h-3 w-3" />
        <span class="sr-only">(sorted {{ $dir === 'asc' ? 'ascending' : 'descending' }})</span>
    @endif
</span>
