<x-app-layout :title="'Edit '.$patient->name" :breadcrumbs="['Patients' => route('patients.index'), $patient->patient_code => route('patients.show', $patient), 'Edit' => null]">
    <form method="POST" action="{{ route('patients.update', $patient) }}" class="card max-w-3xl" novalidate>
        @csrf @method('PUT')
        <div class="card-header">
            <h2 class="card-title">Patient details</h2>
            <span class="text-xs text-slate-500">Issued reports keep the details printed on them.</span>
        </div>
        <div class="card-body grid gap-4 sm:grid-cols-6">
            <div class="sm:col-span-4">
                <label for="name" class="label">Name <span class="text-red-500">*</span></label>
                <input id="name" name="name" value="{{ old('name', $patient->name) }}" required maxlength="150" @class(['input', 'input-error' => $errors->has('name')])>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="sex" class="label">Sex <span class="text-red-500">*</span></label>
                <select id="sex" name="sex" class="input">
                    @foreach ($sexes as $value => $label)
                        <option value="{{ $value }}" @selected(old('sex', $patient->sex) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="age_value" class="label">Age</label>
                <div class="flex">
                    <input id="age_value" name="age_value" type="number" min="0" max="130" value="{{ old('age_value', $patient->age_value) }}" @class(['input rounded-r-none', 'input-error' => $errors->has('age_value')])>
                    <label for="age_unit" class="sr-only">Age unit</label>
                    <select id="age_unit" name="age_unit" class="input w-28 rounded-l-none border-l-0">
                        @foreach ($ageUnits as $value => $label)
                            <option value="{{ $value }}" @selected(old('age_unit', $patient->age_unit ?? 'years') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @error('age_value')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="date_of_birth" class="label">Date of birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $patient->date_of_birth?->format('Y-m-d')) }}" @class(['input', 'input-error' => $errors->has('date_of_birth')])>
                @error('date_of_birth')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label for="phone" class="label">Phone</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone', $patient->phone) }}" maxlength="30" @class(['input', 'input-error' => $errors->has('phone')])>
                @error('phone')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-3">
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $patient->email) }}" maxlength="150" @class(['input', 'input-error' => $errors->has('email')])>
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-3">
                <label for="address" class="label">Address</label>
                <input id="address" name="address" value="{{ old('address', $patient->address) }}" maxlength="255" class="input">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
            <a href="{{ route('patients.show', $patient) }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" /> Save</button>
        </div>
    </form>
</x-app-layout>
