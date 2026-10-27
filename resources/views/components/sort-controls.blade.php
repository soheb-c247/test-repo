{{-- "Sort by" + "Order" selects for a filter bar; applied with the form's Apply button. --}}
@props(['options', 'default', 'defaultDir' => 'desc', 'idPrefix' => 'sort'])
@php
    $sort = array_key_exists((string) request('sort'), $options) ? request('sort') : $default;
    $dir = in_array(request('dir'), ['asc', 'desc'], true) ? request('dir') : $defaultDir;
@endphp
<div {{ $attributes->merge(['class' => 'flex gap-2']) }}>
    <div class="min-w-0 flex-1">
        <label for="{{ $idPrefix }}-by" class="label">Sort by</label>
        <select id="{{ $idPrefix }}-by" name="sort" class="input input-sm">
            @foreach ($options as $value => $label)
                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="w-32">
        <label for="{{ $idPrefix }}-dir" class="label">Order</label>
        <select id="{{ $idPrefix }}-dir" name="dir" class="input input-sm">
            <option value="asc" @selected($dir === 'asc')>Ascending</option>
            <option value="desc" @selected($dir === 'desc')>Descending</option>
        </select>
    </div>
</div>
