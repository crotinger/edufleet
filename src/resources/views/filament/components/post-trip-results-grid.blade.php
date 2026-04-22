@php
    // Shares the exact shape as pre-trip results; structurally identical.
    $record = $getRecord();
    $results = $record?->results()->orderBy('id')->get() ?? collect();
@endphp

@include('filament.components.inspection-results-list', ['results' => $results])
