@php
    $prefix = "results[{$item->id}]";
    $missing = collect($errors->getMessages())->keys()->filter(fn ($k) => str_starts_with($k, "results.{$item->id}."))->count();
@endphp
<article class="card" aria-labelledby="item-{{ $item->id }}-title">
    <header class="card-header bg-slate-50/60">
        <div class="min-w-0">
            <h3 id="item-{{ $item->id }}-title" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-navy-900">
                {{ $item->test_name }}
                <span class="font-mono text-[11px] font-medium text-slate-400">{{ $item->test_code }} · v{{ $item->definition_version }}</span>
            </h3>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ $item->category }}@if ($item->sample_type) · Sample: {{ $item->sample_type }}@endif @if ($item->method) · {{ $item->method }}@endif
            </p>
        </div>
        @if ($missing)
            <span class="badge badge-danger">{{ $missing }} {{ Str::plural('issue', $missing) }}</span>
        @endif
    </header>

    <div class="divide-y divide-slate-100">
        @foreach ($item->sections() as $section)
            <div class="px-5 py-4">
                @if ($section['title'] !== '' && (count($item->sections()) > 1 || $section['title'] !== $item->test_name))
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-brand-700">{{ $section['title'] }}</h4>
                @endif

                <div class="space-y-3">
                    @foreach ($section['parameters'] as $parameter)
                        @php
                            $key = $parameter['key'];
                            $id = "r-{$item->id}-{$key}";
                            $errorKey = "results.{$item->id}.{$key}";
                            $stored = $item->results[$key] ?? null;
                            $value = old("results.{$item->id}.{$key}", $stored);
                            $flag = $item->flags[$key] ?? null;
                            $reference = $evaluator->referenceLabel($parameter, $report->patient_sex, $ageYears);
                            $hasError = $errors->has($errorKey);
                        @endphp

                        @if ($parameter['type'] === 'table')
                            @include('reports.partials.result-table', compact('parameter', 'item', 'prefix', 'value', 'id', 'errorKey'))
                            @continue
                        @endif

                        <div class="grid items-start gap-x-4 gap-y-1 md:grid-cols-[minmax(0,15rem)_minmax(0,1fr)_minmax(0,12rem)]">
                            <label for="{{ $id }}" class="pt-2 text-sm font-medium text-slate-800">
                                {{ $parameter['label'] }}
                                @if ($parameter['required'])<span class="text-red-500" title="Required to finalize">*</span>@endif
                                @if ($parameter['method'])<span class="block text-[11px] font-normal text-slate-400">{{ $parameter['method'] }}</span>@endif
                            </label>

                            <div>
                                @if (! empty($parameter['formula']))
                                    <div class="flex items-center gap-2">
                                        <input id="{{ $id }}" type="text" value="{{ $evaluator->formatValue($parameter, $stored) }}" class="input bg-slate-50 text-slate-600" readonly aria-describedby="{{ $id }}-help">
                                        @if ($parameter['unit'])<span class="whitespace-nowrap text-sm text-slate-500">{{ $parameter['unit'] }}</span>@endif
                                    </div>
                                    <p id="{{ $id }}-help" class="help">Calculated on save: <code class="text-[11px]">{{ $parameter['formula'] }}</code></p>
                                @else
                                    <div class="flex items-center gap-2">
                                        @switch($parameter['type'])
                                            @case('numeric')
                                            @case('decimal')
                                                <input id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" type="text" inputmode="decimal" value="{{ $value }}"
                                                       @class(['input font-mono tabular-nums', 'input-error' => $hasError, 'border-red-300 bg-red-50/40' => ! $hasError && in_array($flag, ['high', 'low'])])
                                                       autocomplete="off" @if ($hasError) aria-invalid="true" @endif>
                                                @break
                                            @case('integer')
                                                <input id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" type="text" inputmode="numeric" pattern="-?[0-9]*" value="{{ $value }}"
                                                       @class(['input font-mono tabular-nums', 'input-error' => $hasError]) autocomplete="off">
                                                @break
                                            @case('select')
                                            @case('qualitative')
                                                <select id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" @class(['input', 'input-error' => $hasError])>
                                                    <option value="">— Select —</option>
                                                    @foreach ($parameter['options'] as $option)
                                                        <option value="{{ $option }}" @selected((string) $value === $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                                @break
                                            @case('multiselect')
                                                <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1.5" role="group" aria-labelledby="{{ $id }}">
                                                    @foreach ($parameter['options'] as $option)
                                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                                            <input type="checkbox" class="checkbox" name="{{ $prefix }}[{{ $key }}][]" value="{{ $option }}" @checked(in_array($option, (array) $value, true))>
                                                            {{ $option }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @break
                                            @case('boolean')
                                                <div class="flex gap-4 pt-1.5" role="radiogroup" aria-labelledby="{{ $id }}">
                                                    <input type="hidden" name="{{ $prefix }}[{{ $key }}]" value="">
                                                    @foreach (['yes' => 'Yes', 'no' => 'No'] as $v => $l)
                                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                                            <input type="radio" class="h-4 w-4 border-slate-300 text-brand-600 focus:ring-brand-500" name="{{ $prefix }}[{{ $key }}]" value="{{ $v }}" @checked($value === $v)>
                                                            {{ $l }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @break
                                            @case('date')
                                                <input id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" type="date" value="{{ $value }}" @class(['input', 'input-error' => $hasError])>
                                                @break
                                            @case('time')
                                                <input id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" type="time" value="{{ $value }}" @class(['input', 'input-error' => $hasError])>
                                                @break
                                            @case('textarea')
                                                <textarea id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" rows="3" maxlength="{{ $parameter['max_length'] ?? 5000 }}" @class(['input', 'input-error' => $hasError])>{{ $value }}</textarea>
                                                @break
                                            @default
                                                <input id="{{ $id }}" name="{{ $prefix }}[{{ $key }}]" type="text" value="{{ $value }}" maxlength="{{ $parameter['max_length'] ?? 255 }}"
                                                       placeholder="{{ $parameter['help'] && str_starts_with($parameter['help'], 'e.g.') ? $parameter['help'] : '' }}"
                                                       @class(['input', 'input-error' => $hasError]) autocomplete="off">
                                        @endswitch
                                        @if ($parameter['unit'] && ! in_array($parameter['type'], ['multiselect', 'boolean', 'textarea']))
                                            <span class="whitespace-nowrap text-sm text-slate-500">{{ $parameter['unit'] }}</span>
                                        @endif
                                    </div>
                                    @error($errorKey)<p class="field-error">{{ $message }}</p>@enderror
                                    @if ($parameter['help'] && ! str_starts_with($parameter['help'], 'e.g.'))<p class="help">{{ $parameter['help'] }}</p>@endif
                                @endif
                            </div>

                            <div class="pt-2 text-xs text-slate-500">
                                @if ($reference !== '')
                                    <span class="block"><span class="font-semibold text-slate-600">Ref:</span> {{ $reference }}</span>
                                @elseif (\App\Support\ParameterTypes::isNumeric($parameter['type']) && empty($parameter['formula']))
                                    <span class="block text-slate-400">No reference interval — not flagged</span>
                                @endif
                                @if ($flag === 'high')
                                    <span class="badge badge-danger mt-1"><x-icon name="arrow-up" class="h-3 w-3" /> Above interval</span>
                                @elseif ($flag === 'low')
                                    <span class="badge mt-1 bg-blue-50 text-blue-700 ring-blue-600/20"><x-icon name="arrow-down" class="h-3 w-3" /> Below interval</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</article>
