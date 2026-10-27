<x-guest-layout title="Sign in">
    <div class="grid min-h-full lg:grid-cols-[1.1fr_1fr]">
        {{-- Brand panel --}}
        <section class="relative hidden overflow-hidden bg-navy-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
            <svg class="pointer-events-none absolute -right-24 -top-24 h-[480px] w-[480px] text-white/[0.04]" viewBox="0 0 64 64" aria-hidden="true">
                <path d="M25 10H39V25H54V39H39V54H25V39H10V25H25Z" fill="currentColor"/>
            </svg>
            <svg class="pointer-events-none absolute -bottom-32 -left-20 h-[420px] w-[420px] text-brand-400/[0.07]" viewBox="0 0 100 100" aria-hidden="true">
                <circle cx="50" cy="50" r="48" fill="none" stroke="currentColor" stroke-width="2"/>
                <circle cx="50" cy="50" r="34" fill="none" stroke="currentColor" stroke-width="2"/>
                <circle cx="50" cy="50" r="20" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>

            <x-logo variant="light" size="md" />

            <div class="relative max-w-md">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-brand-300">Laboratory workspace</p>
                <h1 class="mt-3 text-4xl font-bold leading-tight tracking-tight text-white">
                    {{ config('lab.tagline') }}
                </h1>
                <ul class="mt-8 space-y-4 text-sm text-navy-100">
                    <li class="flex gap-3"><x-icon name="flask" class="mt-0.5 h-5 w-5 shrink-0 text-brand-300" /> Configurable test definitions with units, methods and reference intervals.</li>
                    <li class="flex gap-3"><x-icon name="report" class="mt-0.5 h-5 w-5 shrink-0 text-brand-300" /> Structured result entry with print-ready PDF reports.</li>
                    <li class="flex gap-3"><x-icon name="history" class="mt-0.5 h-5 w-5 shrink-0 text-brand-300" /> Released reports are locked; corrections keep a full revision history.</li>
                </ul>
            </div>

            <p class="relative text-xs text-navy-300">&copy; {{ now()->year }} {{ config('lab.name') }}</p>
        </section>

        {{-- Sign-in form --}}
        <section class="flex items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-sm">
                <x-logo size="md" class="lg:hidden" />

                <h2 class="mt-8 text-2xl font-bold tracking-tight text-navy-900 lg:mt-0">Sign in</h2>
                <p class="mt-1 text-sm text-slate-500">Administrator access to {{ config('lab.name') }}.</p>

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5" novalidate x-data="{ show: false }">
                    @csrf

                    <div>
                        <label for="email" class="label">Email address</label>
                        <div class="relative">
                            <x-icon name="mail" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                   @class(['input pl-9', 'input-error' => $errors->has('email')])
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="label">Password</label>
                        <div class="relative">
                            <x-icon name="lock" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" required autocomplete="current-password"
                                   @class(['input pl-9 pr-10', 'input-error' => $errors->has('password')])
                                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" class="absolute right-1.5 top-1/2 -translate-y-1/2 icon-btn" @click="show = !show"
                                    :aria-label="show ? 'Hide password' : 'Show password'" :aria-pressed="show.toString()">
                                <span x-show="!show"><x-icon name="eye" /></span>
                                <span x-cloak x-show="show"><x-icon name="eye-off" /></span>
                            </button>
                        </div>
                        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="checkbox" @checked(old('remember'))>
                        Keep me signed in on this device
                    </label>

                    <button type="submit" class="btn btn-primary w-full py-2.5">
                        Sign in <x-icon name="chevron-right" />
                    </button>
                </form>

            </div>
        </section>
    </div>
</x-guest-layout>
