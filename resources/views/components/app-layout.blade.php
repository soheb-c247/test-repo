@props(['title' => null, 'breadcrumbs' => [], 'subtitle' => null])
@php
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['route' => 'reports.index', 'match' => 'reports.*', 'label' => 'Reports', 'icon' => 'report'],
        ['route' => 'billing.index', 'match' => 'billing.*', 'label' => 'Billing', 'icon' => 'receipt'],
        ['route' => 'tests.index', 'match' => 'tests.*', 'label' => 'Tests', 'icon' => 'flask'],
        ['route' => 'patients.index', 'match' => 'patients.*', 'label' => 'Patients', 'icon' => 'users'],
    ];
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="h-full font-sans"
      x-data="{ sidebarOpen: false, collapsed: (() => { try { return localStorage.getItem('accucare.sidebar') === 'collapsed' } catch (e) { return false } })() }"
      x-init="$watch('collapsed', v => { try { localStorage.setItem('accucare.sidebar', v ? 'collapsed' : 'open') } catch (e) {} })">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-pop">Skip to content</a>

<div class="flex min-h-full">
    {{-- Mobile backdrop --}}
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-navy-950/50 lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex flex-col bg-navy-900 text-navy-100 transition-all duration-200 lg:translate-x-0"
           :class="[sidebarOpen ? 'translate-x-0' : '-translate-x-full', collapsed ? 'lg:w-[76px]' : 'lg:w-64', 'w-64']"
           aria-label="Main navigation">
        <div class="flex h-16 items-center justify-between gap-2 border-b border-white/10 px-4">
            <a href="{{ route('dashboard') }}" class="flex items-center overflow-hidden" aria-label="{{ config('lab.name') }} dashboard">
                <x-logo variant="light" size="sm" x-bind:class="collapsed ? 'lg:[&>span:last-child]:hidden' : ''" />
            </a>
            <button type="button" class="icon-btn text-navy-200 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebarOpen = false" aria-label="Close navigation">
                <x-icon name="x" />
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['match']); @endphp
                <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $active])
                   @if ($active) aria-current="page" @endif
                   :title="collapsed ? @js($item['label']) : null">
                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                    <span :class="collapsed && 'lg:hidden'">{{ $item['label'] }}</span>
                </a>
            @endforeach

            <div class="pt-5" :class="collapsed && 'lg:hidden'">
                <p class="px-3 text-[11px] font-semibold uppercase tracking-wider text-navy-300">Quick actions</p>
                <a href="{{ route('reports.create') }}" class="nav-link mt-2">
                    <x-icon name="plus" class="h-5 w-5" /> New report
                </a>
                <a href="{{ route('tests.create') }}" class="nav-link">
                    <x-icon name="beaker" class="h-5 w-5" /> New test
                </a>
            </div>
        </nav>

        <div class="hidden border-t border-white/10 p-3 lg:block">
            <button type="button" class="nav-link w-full" @click="collapsed = !collapsed" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <x-icon name="chevron-left" class="h-5 w-5 shrink-0 transition" x-bind:class="collapsed && 'rotate-180'" />
                <span :class="collapsed && 'lg:hidden'">Collapse</span>
            </button>
        </div>
    </aside>

    {{-- Main column --}}
    <div class="flex min-w-0 flex-1 flex-col transition-all duration-200" :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-64'">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="icon-btn lg:hidden" @click="sidebarOpen = true" aria-label="Open navigation">
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <form method="GET" action="{{ route('reports.index') }}" class="relative hidden max-w-md flex-1 sm:block" role="search">
                <label for="global-search" class="sr-only">Search reports</label>
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input id="global-search" data-global-search name="q" type="search" value="{{ request()->routeIs('reports.index') ? request('q') : '' }}"
                       placeholder="Search reports by number, patient or test…" autocomplete="off"
                       class="input border-slate-200 bg-slate-50 pl-9 pr-10 shadow-none focus:bg-white">
                <span class="kbd pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">/</span>
            </form>

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('reports.create') }}" class="btn btn-accent hidden sm:inline-flex">
                    <x-icon name="plus" /> New report
                </a>

                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-lg p-1 pr-2 transition hover:bg-slate-100" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 text-xs font-bold text-white">{{ $user->initials }}</span>
                        <span class="hidden text-left text-sm leading-tight md:block">
                            <span class="block font-semibold text-slate-900">{{ $user->name }}</span>
                            <span class="block text-xs text-slate-500">Administrator</span>
                        </span>
                        <x-icon name="chevron-down" class="hidden text-slate-400 md:block" />
                    </button>
                    <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-pop" role="menu">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                            <x-icon name="user" /> Profile &amp; password
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50" role="menuitem">
                                <x-icon name="logout" /> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            @if ($breadcrumbs || $title)
                <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div class="min-w-0">
                        @if ($breadcrumbs)
                            <nav aria-label="Breadcrumb" class="mb-1">
                                <ol class="flex flex-wrap items-center gap-1 text-xs font-medium text-slate-500">
                                    @foreach ($breadcrumbs as $label => $url)
                                        <li class="flex items-center gap-1">
                                            @if (! $loop->first)<x-icon name="chevron-right" class="h-3 w-3 text-slate-300" />@endif
                                            @if ($url)<a href="{{ $url }}" class="hover:text-navy-900">{{ $label }}</a>@else<span class="text-slate-700">{{ $label }}</span>@endif
                                        </li>
                                    @endforeach
                                </ol>
                            </nav>
                        @endif
                        @if ($title)
                            <h1 class="truncate text-2xl font-bold tracking-tight text-navy-900">{{ $title }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            @endif

            @php
                // Per-field schema messages are shown inside the parameter builder; the
                // banner lists everything else once.
                $summaryErrors = collect($errors->getMessages())
                    ->reject(fn ($messages, $key) => str_starts_with($key, 'schema.'))
                    ->flatten()->unique()->values()->all();
            @endphp
            @if ($summaryErrors)
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                    <p class="flex items-center gap-2 font-semibold"><x-icon name="alert" /> Please correct the highlighted {{ count($summaryErrors) === 1 ? 'field' : 'fields' }}:</p>
                    <ul class="mt-2 list-disc space-y-0.5 pl-6">
                        @foreach (array_slice($summaryErrors, 0, 8) as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                        @if (count($summaryErrors) > 8)
                            <li>…and {{ count($summaryErrors) - 8 }} more (highlighted below).</li>
                        @endif
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 px-4 py-4 text-xs text-slate-400 sm:px-6 lg:px-8">
            {{ config('lab.name') }} · Times shown in {{ config('app.timezone') }}
        </footer>
    </div>
</div>

<x-overlays />
</body>
</html>
