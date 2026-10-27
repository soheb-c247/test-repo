@php
    // Stored table results are a list of rows; index predefined rows by key.
    $rowsByKey = [];
    $extraRows = [];
    if (is_array($value) && array_is_list($value)) {
        foreach ($value as $row) {
            if (! empty($row['key'])) {
                $rowsByKey[$row['key']] = $row['values'] ?? [];
            } else {
                $extraRows[] = ['label' => $row['label'] ?? '', 'values' => $row['values'] ?? []];
            }
        }
    } elseif (is_array($value)) {
        // Old input after a failed save.
        $rowsByKey = $value['rows'] ?? [];
        $extraRows = array_values($value['extra'] ?? []);
    }
    $name = "{$prefix}[{$parameter['key']}]";
@endphp
<div class="rounded-lg border border-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2">
        <p class="text-sm font-medium text-slate-800">
            {{ $parameter['label'] }}
            @if ($parameter['required'])<span class="text-red-500" title="Required to finalize">*</span>@endif
        </p>
        @if ($parameter['reference_text'])
            <p class="text-xs text-slate-500"><span class="font-semibold text-slate-600">Ref:</span> {{ $parameter['reference_text'] }}</p>
        @endif
    </div>
    <div class="relative overflow-x-auto" x-data="tableRows(@js($extraRows))">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="px-3 py-2 font-semibold">{{ $parameter['row_header'] ?? 'Item' }}</th>
                    @foreach ($parameter['columns'] as $column)
                        <th class="px-3 py-2 font-semibold">{{ $column['label'] }}</th>
                    @endforeach
                    @if ($parameter['allow_add_rows'])<th class="w-10"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($parameter['rows'] as $row)
                    <tr>
                        <th scope="row" class="whitespace-nowrap px-3 py-1.5 text-left font-medium text-slate-700">{{ $row['label'] }}</th>
                        @foreach ($parameter['columns'] as $column)
                            @php $cell = $rowsByKey[$row['key']][$column['key']] ?? ''; @endphp
                            <td class="px-3 py-1.5">
                                <label class="sr-only" for="{{ $id }}-{{ $row['key'] }}-{{ $column['key'] }}">{{ $row['label'] }} — {{ $column['label'] }}</label>
                                @if ($column['type'] === 'select')
                                    <select id="{{ $id }}-{{ $row['key'] }}-{{ $column['key'] }}" name="{{ $name }}[rows][{{ $row['key'] }}][{{ $column['key'] }}]" class="input input-sm min-w-[8rem]">
                                        <option value="">—</option>
                                        @foreach ($column['options'] as $option)
                                            <option value="{{ $option }}" @selected((string) $cell === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input id="{{ $id }}-{{ $row['key'] }}-{{ $column['key'] }}" name="{{ $name }}[rows][{{ $row['key'] }}][{{ $column['key'] }}]" value="{{ $cell }}"
                                           type="text" @if ($column['type'] === 'number') inputmode="decimal" @endif class="input input-sm min-w-[7rem]" maxlength="255">
                                @endif
                            </td>
                        @endforeach
                        @if ($parameter['allow_add_rows'])<td></td>@endif
                    </tr>
                @endforeach

                @if ($parameter['allow_add_rows'])
                    <template x-for="(row, index) in rows" :key="row.id">
                        <tr>
                            <td class="px-3 py-1.5">
                                <input type="text" class="input input-sm min-w-[9rem]" maxlength="100" placeholder="Row name" aria-label="Row name"
                                       x-model="row.label" :name="'{{ $name }}[extra][' + index + '][label]'">
                            </td>
                            @foreach ($parameter['columns'] as $column)
                                <td class="px-3 py-1.5">
                                    @if ($column['type'] === 'select')
                                        <select class="input input-sm min-w-[8rem]" aria-label="{{ $column['label'] }}" x-model="row.values['{{ $column['key'] }}']"
                                                :name="'{{ $name }}[extra][' + index + '][values][{{ $column['key'] }}]'">
                                            <option value="">—</option>
                                            @foreach ($column['options'] as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="input input-sm min-w-[7rem]" maxlength="255" aria-label="{{ $column['label'] }}"
                                               x-model="row.values['{{ $column['key'] }}']" :name="'{{ $name }}[extra][' + index + '][values][{{ $column['key'] }}]'">
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2"><button type="button" class="icon-btn text-red-500" @click="remove(row.id)" aria-label="Remove row"><x-icon name="trash" /></button></td>
                        </tr>
                    </template>
                @endif
            </tbody>
        </table>
        @if ($parameter['allow_add_rows'])
            <div class="border-t border-slate-100 px-3 py-2">
                <button type="button" class="btn btn-sm btn-ghost" @click="add()"><x-icon name="plus" /> Add row</button>
            </div>
        @endif
    </div>
    @error($errorKey)<p class="field-error px-3 pb-2">{{ $message }}</p>@enderror
</div>
