{{-- Toast notifications --}}
<div class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6"
     x-data
     x-init="
        @if (session('success')) $store.toasts.push('success', @js(session('success'))); @endif
        @if (session('error')) $store.toasts.push('error', @js(session('error')), 8000); @endif
        @if (session('status')) $store.toasts.push('info', @js(session('status'))); @endif
     "
     aria-live="polite">
    <template x-for="toast in $store.toasts.items" :key="toast.id">
        <div x-transition.opacity.duration.200ms
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white p-4 text-sm shadow-pop"
             :class="{
                'border-brand-200': toast.type === 'success',
                'border-red-200': toast.type === 'error',
                'border-slate-200': toast.type === 'info',
             }"
             role="status">
            <span class="mt-0.5 shrink-0" :class="{ 'text-brand-600': toast.type === 'success', 'text-red-600': toast.type === 'error', 'text-navy-500': toast.type === 'info' }">
                <template x-if="toast.type === 'success'"><x-icon name="check-circle" class="h-5 w-5" /></template>
                <template x-if="toast.type === 'error'"><x-icon name="alert" class="h-5 w-5" /></template>
                <template x-if="toast.type === 'info'"><x-icon name="info" class="h-5 w-5" /></template>
            </span>
            <p class="flex-1 text-slate-700" x-text="toast.message"></p>
            <button type="button" class="text-slate-400 hover:text-slate-700" @click="$store.toasts.remove(toast.id)" aria-label="Dismiss">
                <x-icon name="x" />
            </button>
        </div>
    </template>
</div>

{{-- Confirmation dialog (forms with data-confirm) --}}
<div x-data x-cloak x-show="$store.confirm.open" class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center"
     @keydown.escape.window="$store.confirm.cancel()" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div x-show="$store.confirm.open" x-transition.opacity class="absolute inset-0 bg-navy-950/50" @click="$store.confirm.cancel()"></div>
    <div x-show="$store.confirm.open" x-transition
         x-effect="if ($store.confirm.open) $nextTick(() => $el.querySelector('[data-confirm-accept]')?.focus())"
         class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-pop">
        <div class="flex gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                  :class="$store.confirm.danger ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-700'">
                <x-icon name="alert" class="h-5 w-5" />
            </span>
            <div>
                <h2 id="confirm-title" class="text-base font-semibold text-slate-900" x-text="$store.confirm.title"></h2>
                <p class="mt-1 text-sm text-slate-600" x-text="$store.confirm.message"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn btn-secondary" @click="$store.confirm.cancel()">Cancel</button>
            <button type="button" data-confirm-accept class="btn" :class="$store.confirm.danger ? 'btn-danger' : 'btn-primary'"
                    @click="$store.confirm.accept()" x-text="$store.confirm.label"></button>
        </div>
    </div>
</div>
