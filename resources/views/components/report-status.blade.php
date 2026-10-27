@props(['report'])
@if ($report->isFinal())
    <span class="badge badge-final"><x-icon name="check" class="h-3 w-3" />{{ $report->revision > 0 ? 'Finalized · Rev '.$report->revision : 'Finalized' }}</span>
@elseif ($report->isUnderCorrection())
    <span class="badge badge-correction"><x-icon name="edit" class="h-3 w-3" />Correction</span>
@else
    <span class="badge badge-draft"><x-icon name="clock" class="h-3 w-3" />Draft</span>
@endif
