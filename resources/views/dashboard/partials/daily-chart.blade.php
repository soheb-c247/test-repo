{{--
    Stacked columns: finalized (#13a397) at the baseline, not-yet-finalized
    (#eb6834) on top, 2px surface gap between segments, 4px rounded data end,
    bars capped at 24px. Validated pair (dataviz validator, white surface).
--}}
<div role="img" aria-label="Reports per day: {{ collect($days)->map(fn ($d) => $d['label'].' '.$d['total'])->implode(', ') }}">
    <div class="flex">
        {{-- Y axis --}}
        <div class="relative h-48 w-8 shrink-0" aria-hidden="true">
            @for ($i = 0; $i <= 4; $i++)
                <span class="absolute right-2 translate-y-1/2 text-[10px] tabular-nums text-slate-400" style="bottom: {{ $i * 25 }}%">{{ $step * $i }}</span>
            @endfor
        </div>

        {{-- Plot area --}}
        <div class="relative h-48 flex-1">
            @for ($i = 0; $i <= 4; $i++)
                <div class="pointer-events-none absolute inset-x-0 border-t {{ $i === 0 ? 'border-slate-300' : 'border-dashed border-slate-100' }}" style="bottom: {{ $i * 25 }}%" aria-hidden="true"></div>
            @endfor

            <div class="absolute inset-0 flex">
                @foreach ($days as $day)
                    @php
                        $pending = $day['total'] - $day['final'];
                        $height = $day['total'] / $axisMax * 100;
                    @endphp
                    <div class="group relative flex h-full flex-1 items-end justify-center outline-none" x-data="{ tip: false }"
                         @mouseenter="tip = true" @mouseleave="tip = false" @focus="tip = true" @blur="tip = false" tabindex="0"
                         aria-label="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M') }}: {{ $day['final'] }} finalized, {{ $pending }} not finalized">
                        <div class="absolute inset-y-0 left-1/2 w-10 -translate-x-1/2 rounded-md transition group-hover:bg-slate-100/70 group-focus:bg-slate-100/70"></div>

                        @if ($day['total'] > 0)
                            <div class="relative flex w-6 flex-col gap-[2px]" style="height: {{ $height }}%">
                                <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-xs font-semibold tabular-nums text-slate-700">{{ $day['total'] }}</span>
                                @if ($pending > 0)
                                    <div class="w-full rounded-t-[4px]" style="background:#eb6834; flex: {{ $pending }} 1 0%"></div>
                                @endif
                                @if ($day['final'] > 0)
                                    <div @class(['w-full', 'rounded-t-[4px]' => $pending === 0]) style="background:#13a397; flex: {{ $day['final'] }} 1 0%"></div>
                                @endif
                            </div>
                        @endif

                        <div x-cloak x-show="tip" x-transition.opacity
                             class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 w-40 -translate-x-1/2 rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-pop">
                            <p class="font-semibold text-slate-900">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, j M') }}</p>
                            <p class="mt-1 flex justify-between text-slate-600"><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" style="background:#13a397"></span>Finalized</span><span class="font-semibold tabular-nums text-slate-900">{{ $day['final'] }}</span></p>
                            <p class="flex justify-between text-slate-600"><span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" style="background:#eb6834"></span>Not finalized</span><span class="font-semibold tabular-nums text-slate-900">{{ $pending }}</span></p>
                            <p class="mt-1 flex justify-between border-t border-slate-100 pt-1 text-slate-600"><span>Total</span><span class="font-semibold tabular-nums text-slate-900">{{ $day['total'] }}</span></p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- X axis --}}
    <div class="ml-8 mt-2 flex" aria-hidden="true">
        @foreach ($days as $day)
            <span class="flex-1 text-center text-[11px] font-medium {{ $day['label'] === 'Today' ? 'text-navy-900' : 'text-slate-500' }}">{{ $day['label'] }}</span>
        @endforeach
    </div>
</div>
