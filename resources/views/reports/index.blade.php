<x-app-layout title="Reports" :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => null]"
              subtitle="Every report with its status. Drafts can be edited; finalized reports are locked and corrected through revisions.">
    <x-slot:actions>
        <a href="{{ route('reports.create') }}" class="btn btn-accent"><x-icon name="plus" /> Create report</a>
    </x-slot:actions>

    <div class="card">
        @include('reports.partials.filters', ['action' => route('reports.index'), 'filters' => $filters, 'showCategory' => true, 'target' => '#report-results'])
        <div id="report-results">@include('reports.partials.table', ['reports' => $reports])</div>
    </div>
</x-app-layout>
