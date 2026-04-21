<div class="space-y-4 text-sm">
    <div class="grid gap-3 grid-cols-2">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending jobs</div>
            <div class="text-2xl font-semibold {{ $pending > 0 ? 'text-primary-600' : 'text-gray-500' }}">
                @if ($pending < 0)
                    <span class="text-danger-600">?</span>
                @else
                    {{ $pending }}
                @endif
            </div>
            @if ($pending < 0)
                <div class="text-xs text-danger-600">Could not query Redis</div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Failed jobs</div>
            <div class="text-2xl font-semibold {{ $failed > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $failed }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Students missing coords</div>
            <div class="text-2xl font-semibold {{ $missing > 0 ? 'text-warning-600' : 'text-success-600' }}">{{ $missing }}</div>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Students w/ last error</div>
            <div class="text-2xl font-semibold {{ $erroredStudents > 0 ? 'text-danger-600' : 'text-gray-500' }}">{{ $erroredStudents }}</div>
        </div>
    </div>

    @if ($recentErrors->count() > 0)
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Recent errors</div>
            <ul class="space-y-2">
                @foreach ($recentErrors as $s)
                    <li class="rounded border border-danger-500/30 bg-danger-50 dark:bg-danger-500/10 p-2 text-xs">
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $s->last_name }}, {{ $s->first_name }}</div>
                        <div class="text-gray-600 dark:text-gray-400">{{ $s->home_address ?? '(no address)' }}</div>
                        <div class="mt-1 text-danger-700 dark:text-danger-300">{{ $s->last_geocode_error }}</div>
                        @if ($s->last_geocode_attempted_at)
                            <div class="text-[11px] text-gray-500 mt-1">Attempted {{ $s->last_geocode_attempted_at->diffForHumans() }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-lg border-l-4 border-info-500 bg-info-50 dark:bg-info-500/10 p-3 text-xs space-y-1">
        <div><strong>No worker running?</strong> Pending jobs will stack up forever. Check with:</div>
        <pre class="bg-gray-900 text-gray-100 rounded p-2 overflow-x-auto">docker compose ps | grep worker
docker compose logs -f worker</pre>
        <div><strong>Test the geocoder directly</strong> to confirm network + Nominatim:</div>
        <pre class="bg-gray-900 text-gray-100 rounded p-2 overflow-x-auto">docker compose exec app php artisan geocoder:test "123 Main St, Your City, KS"</pre>
        <div><strong>Retry failed jobs</strong> after fixing the underlying cause:</div>
        <pre class="bg-gray-900 text-gray-100 rounded p-2 overflow-x-auto">docker compose exec app php artisan queue:retry all</pre>
    </div>
</div>
