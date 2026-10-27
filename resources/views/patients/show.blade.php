<x-app-layout :title="$patient->name" :breadcrumbs="['Patients' => route('patients.index'), $patient->patient_code => null]">
    <x-slot:actions>
        <a href="{{ route('patients.edit', $patient) }}" class="btn btn-secondary"><x-icon name="edit" /> Edit details</a>
        <a href="{{ route('reports.create', ['patient' => $patient->id]) }}" class="btn btn-accent"><x-icon name="plus" /> New report</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[20rem_minmax(0,1fr)]">
        <section class="card self-start" aria-labelledby="pd-title">
            <div class="card-header"><h2 id="pd-title" class="card-title">Patient details</h2>@if ($patient->is_demo)<span class="badge badge-neutral">Demo</span>@endif</div>
            <dl class="card-body grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-slate-500">Patient ID</dt><dd class="font-mono font-semibold">{{ $patient->patient_code }}</dd>
                <dt class="text-slate-500">Age</dt><dd>{{ $patient->age_label }}</dd>
                <dt class="text-slate-500">Date of birth</dt><dd>{{ $patient->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                <dt class="text-slate-500">Sex</dt><dd>{{ $patient->sex_label }}</dd>
                <dt class="text-slate-500">Phone</dt><dd>{{ $patient->phone ?: '—' }}</dd>
                <dt class="text-slate-500">Email</dt><dd class="break-all">{{ $patient->email ?: '—' }}</dd>
                <dt class="text-slate-500">Address</dt><dd>{{ $patient->address ?: '—' }}</dd>
                <dt class="text-slate-500">Registered</dt><dd>{{ $patient->created_at?->format('d M Y') }}</dd>
            </dl>
        </section>

        <section class="card" aria-labelledby="pr-title">
            <div class="card-header"><h2 id="pr-title" class="card-title">Reports</h2><span class="text-xs text-slate-500">{{ $reports->total() }} total</span></div>
            @include('reports.partials.table', ['reports' => $reports])
        </section>
    </div>
</x-app-layout>
