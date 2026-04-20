<x-filament-panels::page>
    <div class="prose prose-sm dark:prose-invert max-w-none space-y-0">
        <p class="text-gray-600 dark:text-gray-400">
            Reference material for the edufleet fleet management app. If a field or screen is confusing, start here — or hover the
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block h-4 w-4 align-text-bottom"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
            info icons that appear next to form field labels.
        </p>
    </div>

    {{-- KSDE basics --}}
    <x-filament::section>
        <x-slot name="heading">KSDE transportation reimbursement — the basics</x-slot>
        <x-slot name="description">Kansas State Department of Education pays districts for student transportation based on a formula — not every trip counts.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <ul>
                <li><strong>Eligible rider</strong> — a student living <strong>2.5 miles or more</strong> from their attendance center (or inside 2.5 mi if on a board-approved hazardous route). These are the students the state pays the district to transport.</li>
                <li><strong>Ineligible rider</strong> — a student living inside 2.5 mi without a hazardous-route designation. Districts commonly still pick them up (courtesy rider), but <em>the state does not reimburse for their miles</em>.</li>
                <li><strong>Daily-route trips</strong> (trip_type = <code>daily_route</code>) are the only trips that feed the KSDE claim. Athletic, field, activity, and maintenance trips are paid from other funds (activity budget, gate receipts, field-trip fees, general fund).</li>
                <li><strong>Rider-miles</strong> — the report column and KSDE reimbursement basis. For each trip it's <code>miles × eligible riders</code>; for the whole period it's the sum of those.</li>
                <li>The report mechanic lives at <a href="{{ \App\Filament\Pages\KsdeReport::getUrl() }}" class="text-primary-600 hover:underline">KSDE mileage report</a> — pick a date range, review the per-route and per-vehicle rollups, and hit <strong>Export CSV</strong> when you're ready to submit.</li>
            </ul>
        </div>
    </x-filament::section>

    {{-- Roles --}}
    <x-filament::section>
        <x-slot name="heading">Roles — who sees what</x-slot>
        <x-slot name="description">Role-based access is enforced at the resource level (can the user see the page?) and the row level on Trips (drivers see only their own).</x-slot>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Purpose</th>
                        <th class="px-3 py-2">Can</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr><td class="px-3 py-2 align-top"><span class="fi-badge bg-danger-600 text-white px-2 py-0.5 rounded text-xs">super-admin</span></td><td class="px-3 py-2 align-top">IT / primary administrator</td><td class="px-3 py-2 align-top">Everything. Bypass all permission checks. Manage users and roles.</td></tr>
                    <tr><td class="px-3 py-2 align-top"><span class="fi-badge bg-warning-500 text-white px-2 py-0.5 rounded text-xs">transportation-director</span></td><td class="px-3 py-2 align-top">Runs the transportation department day-to-day</td><td class="px-3 py-2 align-top">Full CRUD on vehicles, drivers, trips, routes, inspections, registrations, maintenance. Read-only on user/role management.</td></tr>
                    <tr><td class="px-3 py-2 align-top"><span class="fi-badge bg-info-500 text-white px-2 py-0.5 rounded text-xs">mechanic</span></td><td class="px-3 py-2 align-top">Shop / maintenance crew</td><td class="px-3 py-2 align-top">CRUD on vehicles, inspections, maintenance records. Read-only on trips (to see who drove what). No access to drivers / routes / registrations / users.</td></tr>
                    <tr><td class="px-3 py-2 align-top"><span class="fi-badge bg-success-500 text-white px-2 py-0.5 rounded text-xs">driver</span></td><td class="px-3 py-2 align-top">Bus driver logging trips</td><td class="px-3 py-2 align-top">Read-only on vehicles and routes. Can view + create <em>their own</em> trips (Driver field is locked to them; cannot see other drivers' trips).</td></tr>
                    <tr><td class="px-3 py-2 align-top"><span class="fi-badge bg-gray-500 text-white px-2 py-0.5 rounded text-xs">viewer</span></td><td class="px-3 py-2 align-top">Auditors, superintendents, board members</td><td class="px-3 py-2 align-top">Read-only on everything. No create / edit / delete. Cannot see the KSDE report unless also granted view_any_route (or stacked with another role).</td></tr>
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">A user can hold multiple roles. Having any one of these roles (plus a link to a Driver record, if driver-only) is required to log into the admin panel.</p>
    </x-filament::section>

    {{-- Expiration color legend --}}
    <x-filament::section>
        <x-slot name="heading">Expiration color legend</x-slot>
        <x-slot name="description">All expiration columns (CDL, DOT medical, inspections, registrations, maintenance due) follow the same color rules.</x-slot>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-danger-500/30 bg-danger-50 p-3 dark:bg-danger-500/10">
                <div class="font-semibold text-danger-700 dark:text-danger-300">Red — expired</div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Already past the expiration date. Address immediately; driver should not operate / vehicle should not move.</div>
            </div>
            <div class="rounded-lg border border-warning-500/30 bg-warning-50 p-3 dark:bg-warning-500/10">
                <div class="font-semibold text-warning-700 dark:text-warning-300">Amber — &lt; 30 days</div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Expiring soon. Schedule the renewal / inspection now.</div>
            </div>
            <div class="rounded-lg border border-info-500/30 bg-info-50 p-3 dark:bg-info-500/10">
                <div class="font-semibold text-info-700 dark:text-info-300">Blue — 30–90 days</div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">On the horizon. Plan for it next month.</div>
            </div>
            <div class="rounded-lg border border-success-500/30 bg-success-50 p-3 dark:bg-success-500/10">
                <div class="font-semibold text-success-700 dark:text-success-300">Green — &gt; 90 days</div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Healthy. No action needed.</div>
            </div>
        </div>
    </x-filament::section>

    {{-- Domain glossary --}}
    <x-filament::section collapsible>
        <x-slot name="heading">Glossary — fleet terms</x-slot>

        <dl class="grid gap-x-6 gap-y-4 md:grid-cols-2">
            <div>
                <dt class="font-semibold">CDL class A / B / C</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">A: combination vehicles over 26k lb towing over 10k lb. B: single vehicle over 26k lb (most school buses). C: under 26k lb with passenger or hazmat endorsement.</dd>
            </div>
            <div>
                <dt class="font-semibold">Endorsements (P, S, T, N, H, X)</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">P = Passenger (required over 16 including driver). S = School Bus (required + background check). T = Double/triple trailers. N = Tank. H = Hazmat. X = combined N+H.</dd>
            </div>
            <div>
                <dt class="font-semibold">Restrictions (L, Z, etc.)</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Codes on the back of the license limiting what the driver can operate. Common: L (no air brakes), Z (no full air brakes), 1 (corrective lenses).</dd>
            </div>
            <div>
                <dt class="font-semibold">DOT medical card</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Federal medical examiner's certificate. CDL holders must hold a current one. Max 2-year validity.</dd>
            </div>
            <div>
                <dt class="font-semibold">KHP annual inspection</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Kansas Highway Patrol safety inspection, required annually for every school bus. Produces the sticker on the windshield.</dd>
            </div>
            <div>
                <dt class="font-semibold">Propane tank inspection</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Required annually for propane-fueled buses. Done by a certified propane inspector, not the shop.</dd>
            </div>
            <div>
                <dt class="font-semibold">Route (edufleet meaning)</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">A <em>template</em> for a repeating trip — default vehicle, default driver, schedule, estimated miles. Each actual run becomes a Trip record linked back to the route.</dd>
            </div>
            <div>
                <dt class="font-semibold">Trip</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">One actual vehicle dispatch. Has a vehicle, a driver, start/end timestamps, start/end odometers, and ridership. May or may not be tied to a Route.</dd>
            </div>
            <div>
                <dt class="font-semibold">In-progress trip</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">A trip with <code>started_at</code> but no <code>ended_at</code>. The driver hasn't closed it out yet. Completing it (adding end_odometer + ended_at) auto-bumps the vehicle's current odometer reading.</dd>
            </div>
            <div>
                <dt class="font-semibold">Rider-miles</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">For each trip: <code>miles × eligible riders</code>. Summed across a reporting period, this is the reimbursement basis on the KSDE annual transportation form.</dd>
            </div>
        </dl>
    </x-filament::section>

    {{-- Common workflows --}}
    <x-filament::section collapsible>
        <x-slot name="heading">Common workflows</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>Log a new trip</h4>
            <ol>
                <li>Go to <a href="{{ \App\Filament\Resources\Trips\TripResource::getUrl('create') }}" class="text-primary-600 hover:underline">Trips → New</a>.</li>
                <li>If this is a recurring route, pick it from the Route dropdown — vehicle, driver, trip type, purpose, and start odometer all prefill.</li>
                <li>Fill in riders on board when the trip ends. For route trips, split into eligible / ineligible so the KSDE report is accurate.</li>
                <li>Set <code>ended_at</code> and <code>end_odometer</code> when the trip is done. Leave blank while the bus is still out.</li>
            </ol>

            <h4>Complete an in-progress trip</h4>
            <ol>
                <li>Dashboard shows "N in progress" on the Trips &amp; mileage card.</li>
                <li>Open <a href="{{ \App\Filament\Resources\Trips\TripResource::getUrl('index') }}" class="text-primary-600 hover:underline">Trips</a> and apply the "In progress" filter.</li>
                <li>Edit the trip, fill in <code>ended_at</code> + <code>end_odometer</code>, save. The vehicle's odometer auto-updates, the maintenance widget recomputes, and the trip drops out of the in-progress filter.</li>
            </ol>

            <h4>Add a new driver</h4>
            <ol>
                <li>Go to <a href="{{ \App\Filament\Resources\Drivers\DriverResource::getUrl('create') }}" class="text-primary-600 hover:underline">Drivers → New</a>.</li>
                <li>Identity + CDL sections are the critical ones. For buses, set Class B with P + S endorsements.</li>
                <li>Enter all expiration dates — the dashboard widgets and the in-list badges depend on them.</li>
                <li>If the driver will log into edufleet, create a User with the <code>driver</code> role (Users &amp; roles page), then come back to the Driver and link it via "Login account".</li>
            </ol>

            <h4>Run the KSDE mileage report</h4>
            <ol>
                <li>Open <a href="{{ \App\Filament\Pages\KsdeReport::getUrl() }}" class="text-primary-600 hover:underline">KSDE mileage report</a>.</li>
                <li>Default is the current month. Change the start / end dates to match the KSDE reporting period (usually a school month or the whole year).</li>
                <li>Review the <em>Reimbursable totals</em> block — this is what goes on the form.</li>
                <li>Click <strong>Export CSV</strong> for the raw numbers, or use the browser's Print → Save as PDF for a paper copy.</li>
            </ol>
        </div>
    </x-filament::section>

    {{-- Dashboard widget cheat-sheet --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Dashboard widgets — what each one shows</x-slot>

        <dl class="grid gap-4 md:grid-cols-2">
            <div>
                <dt class="font-semibold">Expirations overview</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Count of CDL licenses, DOT medicals, inspections, registrations expiring in the next 30 days. Red stat = something already expired.</dd>
            </div>
            <div>
                <dt class="font-semibold">Trips &amp; mileage</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">This-week trip count + miles, month-to-date miles, in-progress trips awaiting an end odometer.</dd>
            </div>
            <div>
                <dt class="font-semibold">Upcoming expirations (inspections)</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Next 10 vehicle inspections due (sooner = more urgent). Colored by proximity.</dd>
            </div>
            <div>
                <dt class="font-semibold">Maintenance coming due</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Service items approaching their next-due date or mile marker. Includes items already past due. Triggers on either date (within 30 days) or mileage (within 500 mi).</dd>
            </div>
            <div>
                <dt class="font-semibold">Ridership by route</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">Per-route rollup of this month's daily-route trips — miles, eligible / ineligible riders, rider-miles. The reimbursable subset.</dd>
            </div>
            <div>
                <dt class="font-semibold">Trip miles by type</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">All trip types for the month — daily_route, athletic, field_trip, activity, maintenance. Captures what the fleet is doing beyond KSDE-eligible work.</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
