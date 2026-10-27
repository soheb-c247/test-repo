@props(['variant' => 'dark', 'size' => 'md', 'wordmark' => true])
@php
    $mark = ['sm' => 'h-8 w-8', 'md' => 'h-10 w-10', 'lg' => 'h-14 w-14'][$size] ?? 'h-10 w-10';
    $title = $variant === 'light' ? 'text-white' : 'text-navy-900';
    $sub = $variant === 'light' ? 'text-brand-300' : 'text-brand-600';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-3']) }}>
    <svg class="{{ $mark }} shrink-0" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="64" height="64" rx="16" fill="#0B2A4A"/>
        @if ($variant === 'light')
            <rect x="0.75" y="0.75" width="62.5" height="62.5" rx="15.25" fill="none" stroke="#ffffff" stroke-opacity=".14" stroke-width="1.5"/>
        @endif
        <path d="M25 10H39V25H54V39H39V54H25V39H10V25H25Z" fill="#14B8A6"/>
        <path d="M28 20H36V29.5L43.2 42.2C44.3 44.2 42.9 46.6 40.6 46.6H23.4C21.1 46.6 19.7 44.2 20.8 42.2L28 29.5Z" fill="#fff"/>
        <path d="M24.3 37.6H39.7L42.4 42.4C42.9 43.4 42.2 44.6 41.1 44.6H22.9C21.8 44.6 21.1 43.4 21.6 42.4Z" fill="#0EA5A4"/>
        <rect x="26.5" y="18" width="11" height="3.2" rx="1.6" fill="#fff"/>
        <circle cx="29.5" cy="34" r="1.4" fill="#0EA5A4"/>
        <circle cx="33.6" cy="31.2" r="1" fill="#0EA5A4"/>
    </svg>
    @if ($wordmark)
        <span class="flex flex-col leading-none">
            <span class="{{ $title }} text-[17px] font-extrabold tracking-tight">Accu<span class="{{ $variant === 'light' ? 'text-brand-300' : 'text-brand-600' }}">Care</span></span>
            <span class="{{ $sub }} mt-1 text-[10px] font-semibold uppercase tracking-[0.18em]">Pathology Lab</span>
        </span>
    @endif
</span>
