@props(['bill'])
@php
    [$class, $icon] = match ($bill->status) {
        'paid' => ['badge-final', 'check'],
        'partial' => ['badge-warn', 'clock'],
        'cancelled' => ['badge-neutral', 'x'],
        default => ['badge-danger', 'alert'],
    };
@endphp
<span class="badge {{ $class }}"><x-icon :name="$icon" class="h-3 w-3" />{{ $bill->status_label }}</span>
