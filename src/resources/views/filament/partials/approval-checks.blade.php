@php
    /** @var array<int, string> $issues */
    $issues = $issues ?? [];
@endphp

@if (empty($issues))
    <div class="rounded-lg border border-success-500/30 bg-success-50 dark:bg-success-500/10 p-3">
        <div class="flex items-start gap-2 text-sm text-success-800 dark:text-success-200">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5 shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            <div>
                <div class="font-semibold">No conflicts detected</div>
                <div class="text-xs text-success-700/80 dark:text-success-300/80 mt-0.5">Vehicle is available and fits the passenger count.</div>
            </div>
        </div>
    </div>
@else
    <div class="rounded-lg border border-warning-500/40 bg-warning-50 dark:bg-warning-500/10 p-3">
        <div class="flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5 shrink-0 mt-0.5 text-warning-700 dark:text-warning-300"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
            <div class="flex-1">
                <div class="font-semibold text-warning-800 dark:text-warning-200">
                    {{ count($issues) }} issue{{ count($issues) === 1 ? '' : 's' }} with this assignment
                </div>
                <ul class="mt-1.5 space-y-1 text-xs text-warning-900 dark:text-warning-100">
                    @foreach ($issues as $i)
                        <li class="flex gap-1.5"><span>•</span><span>{{ $i }}</span></li>
                    @endforeach
                </ul>
                <div class="text-xs text-warning-700 dark:text-warning-300 mt-2 italic">
                    Pick a different vehicle, adjust the times, or check the override box below to approve anyway.
                </div>
            </div>
        </div>
    </div>
@endif
