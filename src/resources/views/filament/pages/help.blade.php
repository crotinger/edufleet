<x-filament-panels::page>
    <div class="prose prose-sm dark:prose-invert max-w-none">
        <p class="text-gray-600 dark:text-gray-400">
            Operator's manual for edufleet. Everything a new admin, mechanic, or driver needs to get oriented. Sections are collapsible — expand the ones that apply.
        </p>
    </div>

    {{-- Getting started --}}
    <x-filament::section>
        <x-slot name="heading">Getting started</x-slot>
        <x-slot name="description">First things to do on a fresh install.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <ol>
                <li><strong>Sign in</strong> with the admin credentials you created during install (see the <em>Setup &amp; deployment</em> section below if you haven't yet).</li>
                <li><strong>Create your real users</strong> at <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" class="text-primary-600 hover:underline">Admin → Users</a>. One per person who should log in — don't share accounts.</li>
                <li><strong>Assign each user a role.</strong> Most people need exactly one. See the matrix below. Multiple roles stack (unions of permissions).</li>
                <li><strong>Load vehicles and drivers</strong> — either one at a time (New button) or in bulk via <strong>Import CSV</strong> on each list page.</li>
                <li><strong>Create routes</strong> for the repeating school-day runs. Each Route is a template; daily trips copy vehicle, driver, schedule, and miles from it.</li>
                <li><strong>Enter open inspections, registrations, and maintenance records</strong> so the expiration widgets and KSDE report start reflecting reality.</li>
                <li><strong>Print PIN/QR labels</strong> for each vehicle from <a href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl() }}" class="text-primary-600 hover:underline">Vehicles</a> → edit → "Quicktrip label" action, and tape one inside each cab so drivers can log trips from their phones.</li>
            </ol>
        </div>
    </x-filament::section>

    {{-- Roles --}}
    <x-filament::section>
        <x-slot name="heading">Roles &amp; permissions</x-slot>
        <x-slot name="description">Access is role-based. A user needs at least one role to reach the admin panel.</x-slot>

        <div class="w-full overflow-x-auto">
            <table class="w-full table-fixed text-sm">
                <colgroup>
                    <col style="width: 12rem">
                    <col style="width: 16rem">
                    <col>
                </colgroup>
                <thead>
                    <tr class="text-left">
                        <th class="px-3 py-2 font-semibold">Role</th>
                        <th class="px-3 py-2 font-semibold">Intended user</th>
                        <th class="px-3 py-2 font-semibold">Can do</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr>
                        <td class="px-3 py-2 align-top"><span class="bg-danger-600 text-white px-2 py-0.5 rounded text-xs whitespace-nowrap inline-block">super-admin</span></td>
                        <td class="px-3 py-2 align-top break-words">IT / primary administrator.</td>
                        <td class="px-3 py-2 align-top break-words">Everything. Bypasses all permission checks. Manages users, roles, and role-to-permission assignments.</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 align-top"><span class="bg-warning-500 text-white px-2 py-0.5 rounded text-xs whitespace-nowrap inline-block">transportation-director</span></td>
                        <td class="px-3 py-2 align-top break-words">Runs the transportation department day-to-day.</td>
                        <td class="px-3 py-2 align-top break-words">Full CRUD on vehicles, drivers, trips, routes, inspections, registrations, maintenance, reservations, trip requests. Approves/denies teacher requests. Views audit log. Cannot manage users or roles.</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 align-top"><span class="bg-info-500 text-white px-2 py-0.5 rounded text-xs whitespace-nowrap inline-block">mechanic</span></td>
                        <td class="px-3 py-2 align-top break-words">Shop crew.</td>
                        <td class="px-3 py-2 align-top break-words">CRUD on vehicles, inspections, maintenance records and schedules. Read-only on trips (so they see who drove what and current odometer). Can view Vehicle availability.</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 align-top"><span class="bg-success-500 text-white px-2 py-0.5 rounded text-xs whitespace-nowrap inline-block">driver</span></td>
                        <td class="px-3 py-2 align-top break-words">Bus / van driver logging their own trips.</td>
                        <td class="px-3 py-2 align-top break-words">Read-only on vehicles and routes. Can view + create <em>their own</em> trips (Driver field is locked; cannot see other drivers' trips). Can create fuel-log entries.</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 align-top"><span class="bg-primary-500 text-white px-2 py-0.5 rounded text-xs whitespace-nowrap inline-block">teacher</span></td>
                        <td class="px-3 py-2 align-top break-words">Teacher / sponsor booking a vehicle.</td>
                        <td class="px-3 py-2 align-top break-words">Submits and edits their own Trip requests. Sees Vehicle availability to plan around existing reservations. Cannot see other trip records or admin data.</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 align-top"><span class="bg-gray-500 text-white px-2 py-0.5 rounded text-xs whitespace-nowrap inline-block">viewer</span></td>
                        <td class="px-3 py-2 align-top break-words">Superintendent, auditor, board member.</td>
                        <td class="px-3 py-2 align-top break-words">Read-only on everything. No create / edit / delete. Good "peek" access for reviewers who should not be able to change data.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">A user can hold multiple roles — permissions union. Changes to role-to-permission assignments take effect immediately (permission cache is cleared on save).</p>
    </x-filament::section>

    {{-- User management --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">User maintenance — add, remove, reset</x-slot>
        <x-slot name="description">Day-to-day account operations. Super-admin only.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>Add a new user</h4>
            <ol>
                <li>Open <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('create') }}" class="text-primary-600 hover:underline">Admin → Users → New</a>.</li>
                <li>Enter name, email (used as the login), and a temporary password.</li>
                <li>Attach one or more roles. Most people need exactly one. Drivers additionally need a linked Driver record — see below.</li>
                <li>Share the credentials with the user and ask them to change the password on first login.</li>
            </ol>

            <h4>Link a User to a Driver record</h4>
            <p>Drivers only see trips where they are the assigned driver. For that to work, the User account must be linked to the Driver row:</p>
            <ol>
                <li>Create / locate the Driver at <a href="{{ \App\Filament\Resources\Drivers\DriverResource::getUrl() }}" class="text-primary-600 hover:underline">Drivers</a>.</li>
                <li>Edit the Driver and set <strong>Login account</strong> to the matching User.</li>
                <li>Assign the User the <code>driver</code> role if not already set.</li>
            </ol>

            <h4>Reset a forgotten password</h4>
            <p>There is no self-serve "forgot password" email flow yet. An admin sets a temporary password on the User record; the user changes it after logging in.</p>

            <h4>Deactivate / remove a user</h4>
            <p>Delete the User record (soft-deleted — row stays for audit history, but the account cannot log in). If the person is a driver who is on leave or terminated, also set the Driver's <code>status</code> accordingly — trip logging for that driver will still function historically but they'll show as Inactive in lists.</p>

            <h4>Change what a role can do</h4>
            <p>Roles are data, not code. A super-admin can edit <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}" class="text-primary-600 hover:underline">Admin → Roles</a> to add or drop permissions. Changes take effect on next page load.</p>
        </div>
    </x-filament::section>

    {{-- Common workflows --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Day-to-day workflows</x-slot>
        <x-slot name="description">Step-by-step for the most common operations.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>Log a new trip</h4>
            <ol>
                <li>Go to <a href="{{ \App\Filament\Resources\Trips\TripResource::getUrl('create') }}" class="text-primary-600 hover:underline">Trips → New</a>.</li>
                <li>If this is a recurring route, pick it from the Route dropdown — vehicle, driver, trip type, purpose, and start odometer all prefill.</li>
                <li>Fill ridership on board when the trip ends. For route trips, split eligible / ineligible so the KSDE report is accurate.</li>
                <li>Set <code>ended_at</code> and <code>end_odometer</code> when the trip is done. Leave blank while the bus is still out — the dashboard surfaces it as "in progress".</li>
            </ol>

            <h4>Close an in-progress trip</h4>
            <ol>
                <li>The dashboard <em>Trips &amp; mileage</em> card shows "N in progress".</li>
                <li>Open <a href="{{ \App\Filament\Resources\Trips\TripResource::getUrl('index') }}" class="text-primary-600 hover:underline">Trips</a>, apply the "In progress" filter.</li>
                <li>Edit the trip, fill in <code>ended_at</code> and <code>end_odometer</code>, save. The vehicle's odometer auto-updates, maintenance widget recomputes, and the trip drops out of the filter.</li>
            </ol>

            <h4>Approve a teacher's vehicle request</h4>
            <ol>
                <li>A teacher submits at <a href="{{ \App\Filament\Resources\TripRequests\TripRequestResource::getUrl() }}" class="text-primary-600 hover:underline">Operations → Trip requests</a>. Pending ones land in the Requested tab.</li>
                <li>Open the request. The form shows conflict checks: any overlapping reservations or trips on the same vehicle, any capacity shortfall (expected passengers vs vehicle capacity).</li>
                <li>Either <strong>Approve</strong> (turns the request into a <code>reserved</code> reservation), <strong>Split across vehicles</strong> (for group sizes that don't fit one vehicle — 20 passengers → two 12-pax vans), or <strong>Deny</strong> with a reason.</li>
                <li>When the teacher picks up the keys, change the reservation to <code>claimed</code>. When it comes back, <code>returned</code>.</li>
            </ol>

            <h4>Issue keys without a request (admin-issued)</h4>
            <ol>
                <li>Go to <a href="{{ \App\Filament\Resources\TripReservations\TripReservationResource::getUrl('create') }}" class="text-primary-600 hover:underline">Operations → Reservations → New</a>.</li>
                <li>Pick vehicle, requested-by user, expected return, and set source = <code>admin_issue</code>. Status lands at <code>reserved</code>.</li>
                <li>Mark <code>claimed</code> when the keys leave the board; <code>returned</code> when they come back.</li>
            </ol>

            <h4>Run the KSDE mileage report</h4>
            <ol>
                <li>Open <a href="{{ \App\Filament\Pages\KsdeReport::getUrl() }}" class="text-primary-600 hover:underline">Reports → KSDE mileage report</a>.</li>
                <li>Default is the current month. Change start / end to match the KSDE reporting period.</li>
                <li>Review the <em>Reimbursable totals</em> block — that's what goes on the form.</li>
                <li>Click <strong>Export CSV</strong> for raw numbers, or use the browser's Print → Save as PDF for a paper copy.</li>
            </ol>

            <h4>Renew a CDL, DOT medical, inspection, or registration</h4>
            <ol>
                <li>Watch the dashboard expiration widgets; the <em>Upcoming expirations</em> list sorts by soonest.</li>
                <li>Open the underlying record (Driver / Inspection / Registration) and update the expiration date after the renewal clears.</li>
                <li>For inspections and registrations, add a <em>new</em> record for the renewal rather than editing the old one — historical audit trail matters.</li>
            </ol>
        </div>
    </x-filament::section>

    {{-- Module reference --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Module reference</x-slot>
        <x-slot name="description">What each screen is for, and the non-obvious fields.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>Vehicles</h4>
            <p>The fleet roster. One row per vehicle the district operates.</p>
            <ul>
                <li><strong>Type</strong> = <code>bus</code> or <code>light_vehicle</code> — affects which inspections and capacity defaults apply.</li>
                <li><strong>Odometer</strong> is canonical — auto-updated from the highest <code>end_odometer</code> on any trip that closes.</li>
                <li><strong>Quicktrip PIN</strong> + <strong>Key barcode</strong> — used by the public PWA to authenticate a driver scanning the cab-mounted QR. Print the pair on the in-cab label.</li>
                <li><strong>Status</strong> — <code>active</code>, <code>in_shop</code>, <code>retired</code>. <code>in_shop</code> hides the vehicle from availability / reservation flows.</li>
            </ul>

            <h4>Drivers</h4>
            <p>People authorized to drive. Independent of User accounts — a Driver may or may not have a login.</p>
            <ul>
                <li><strong>License class + endorsements</strong> drive which vehicles they can be assigned to (Class B + P + S for most bus work).</li>
                <li>All expiration dates (CDL, DOT medical, first aid, defensive driving) feed the same dashboard/widget coloring.</li>
                <li><strong>Login account</strong> — optional link to a User row; required if the driver uses the admin panel or the quicktrip PWA against their own trip list.</li>
            </ul>

            <h4>Routes</h4>
            <p>Templates for repeating school-day runs (Route 1 Morning, Route 3 Afternoon, etc.). Not actual trips — each real dispatch creates a Trip referencing the Route.</p>
            <ul>
                <li><strong>Default vehicle / driver</strong> prefill any Trip linked to this route.</li>
                <li><strong>Estimated miles</strong> is the KSDE-reported mileage until a real odometer reading replaces it on the Trip.</li>
                <li><strong>Days of week</strong> — drives the reservation conflict checker; a Route marked mon-fri reserves that vehicle on those days.</li>
            </ul>

            <h4>Trips</h4>
            <p>One actual vehicle dispatch. The atom of mileage and ridership tracking.</p>
            <ul>
                <li><strong>trip_type</strong> — <code>daily_route</code>, <code>athletic</code>, <code>field_trip</code>, <code>activity</code>, <code>maintenance</code>. Only <code>daily_route</code> feeds the KSDE claim.</li>
                <li><strong>Ridership eligible / ineligible</strong> — for daily_route trips, the split matters. For athletic/field/activity, put total in <em>ineligible</em> (or in passengers only) — those trips don't reimburse.</li>
                <li>An in-progress trip has <code>started_at</code> set and <code>ended_at</code> null. Finishing the trip records the end odometer and auto-updates the vehicle.</li>
            </ul>

            <h4>Inspections</h4>
            <p>KHP annual inspections (buses), internal safety inspections (light vehicles), propane tank inspections (propane buses). Each has a <code>type</code>, <code>inspected_on</code>, <code>expires_on</code>, and result.</p>
            <ul>
                <li>Add a new record for each renewal — don't edit an old one. The <em>latestInspection</em> relation drives dashboards.</li>
                <li><strong>Result</strong> = <code>passed</code>, <code>passed_with_defects</code>, <code>failed</code>. Defects should be entered as Maintenance records to track resolution.</li>
            </ul>

            <h4>Registrations</h4>
            <p>Annual state vehicle registration. One active row per vehicle (older rows form the audit trail). Expiration feeds the color-coded dashboard widget.</p>

            <h4>Maintenance</h4>
            <p>Two related concepts:</p>
            <ul>
                <li><strong>Maintenance schedule</strong> — the <em>rule</em>: oil change every 5,000 mi or 6 months, brake inspection every 6 months, propane tank inspection annually, etc. One row per (vehicle, service type).</li>
                <li><strong>Maintenance record</strong> — the <em>event</em>: this service was performed on this date at this odometer, cost this much, next due here. Records tick the schedule forward.</li>
            </ul>
            <p>The <a href="{{ \App\Filament\Pages\MaintenanceTimeline::getUrl() }}" class="text-primary-600 hover:underline">Maintenance timeline</a> page projects each vehicle's next due service based on schedule + last record.</p>

            <h4>Trip requests &amp; reservations</h4>
            <p>Two sides of the same table (<code>trip_reservations</code>) filtered by <code>source</code>:</p>
            <ul>
                <li><strong>Trip requests</strong> = <code>teacher_request</code> — pending requests from teachers awaiting admin approval.</li>
                <li><strong>Reservations</strong> = <code>admin_issue</code> (admin handed out keys) or <code>self_service</code> (PWA-generated). Approved teacher requests also appear here after approval.</li>
                <li>Lifecycle: <code>requested</code> → <code>reserved</code> → <code>claimed</code> → <code>returned</code>. Side paths: <code>denied</code>, <code>cancelled</code>, <code>expired</code>.</li>
                <li>Multi-vehicle splits (a 20-passenger request approved across two 12-pax vans) share a <code>split_group_id</code> UUID.</li>
            </ul>

            <h4>Vehicle availability</h4>
            <p><a href="{{ \App\Filament\Pages\VehicleAvailability::getUrl() }}" class="text-primary-600 hover:underline">Vehicle availability</a> — sortable fleet-wide view of current status, active reservations, and upcoming reservations. Use before approving a teacher request.</p>

            <h4>Reservation schedule</h4>
            <p><a href="{{ \App\Filament\Pages\ReservationSchedule::getUrl() }}" class="text-primary-600 hover:underline">Reservation schedule</a> — week calendar of every reservation and daily-route trip across the fleet. Quickest way to spot double-bookings.</p>

            <h4>Audit log</h4>
            <p>Every create / update / delete on the main models lands in <a href="{{ \App\Filament\Resources\ActivityLogs\ActivityLogResource::getUrl() }}" class="text-primary-600 hover:underline">Admin → Audit log</a>, filterable by subject type, actor, and date.</p>
        </div>
    </x-filament::section>

    {{-- Quicktrip PWA --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Quicktrip PWA — the in-cab trip logger</x-slot>
        <x-slot name="description">How drivers log trips from a phone without a full login.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>The label</h4>
            <p>Each vehicle gets a printable 3×4" label with:</p>
            <ul>
                <li>A QR code encoding a <em>signed URL</em> to <code>/quicktrip/{vehicle}</code> (valid indefinitely, but unique to that vehicle).</li>
                <li>A short numeric PIN (also stored on the Vehicle record).</li>
                <li>The unit number and a "do not remove" watermark.</li>
            </ul>
            <p>Generate via the <strong>Quicktrip label</strong> action on a Vehicle edit page, then print and tape inside the cab.</p>

            <h4>The flow</h4>
            <ol>
                <li>Driver scans the in-cab QR with their phone camera. Phone opens <code>/quicktrip/{vehicle}</code> (rate-limited per vehicle).</li>
                <li>Driver enters the PIN. Wrong PIN = throttled.</li>
                <li>Page shows the current state: <em>Start a trip</em>, <em>End the trip</em>, or <em>Not my trip</em>.</li>
                <li><strong>Start</strong> → driver picks themselves, enters trip type + purpose + passengers, submits start odometer. Reservation row is created (source = <code>self_service</code>).</li>
                <li><strong>End</strong> → enters end odometer + eligible/ineligible riders, marks complete. Trip closes; vehicle odometer updates.</li>
                <li><strong>Not my trip</strong> → driver forgot to close a previous trip. Marks the previous reservation <code>cancelled</code> and lets this driver start fresh.</li>
            </ol>

            <h4>Review queue</h4>
            <p>Self-service trips land in the Trips list with <code>status = pending</code>. An admin or director reviews (ridership sensible? odometer monotonically increasing?) and approves. Until then, the trip doesn't feed the KSDE report.</p>
        </div>
    </x-filament::section>

    {{-- CSV import --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">CSV import (vehicles &amp; drivers)</x-slot>
        <x-slot name="description">Bulk-load fleet data from a spreadsheet.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>Where</h4>
            <p><strong>Import CSV</strong> button on <a href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl() }}" class="text-primary-600 hover:underline">Vehicles</a> and <a href="{{ \App\Filament\Resources\Drivers\DriverResource::getUrl() }}" class="text-primary-600 hover:underline">Drivers</a> list pages.</p>

            <h4>How it runs</h4>
            <ol>
                <li>Download the example template from the modal — it has all valid columns and one sample row.</li>
                <li>Fill in your rows, save as CSV (UTF-8).</li>
                <li>Upload. Filament maps header names to columns; fix any mappings that didn't auto-match.</li>
                <li>Import runs in the background queue. When it finishes (usually seconds for a district-sized fleet), a notification shows "N imported, M failed."</li>
                <li>Failed rows get a downloadable CSV with the exact error per row — fix and re-upload.</li>
            </ol>

            <h4>Required columns — Vehicles</h4>
            <ul>
                <li><code>type</code> — <code>bus</code> or <code>light_vehicle</code></li>
                <li><code>unit_number</code> — unique per type</li>
            </ul>

            <h4>Required columns — Drivers</h4>
            <ul>
                <li><code>first_name</code>, <code>last_name</code></li>
                <li>Ideally <code>license_state</code> + <code>license_number</code> (used for deduplication) or <code>employee_id</code></li>
            </ul>

            <h4>Deduplication — re-importing is safe</h4>
            <ul>
                <li>Vehicles dedup on (<code>type</code>, <code>unit_number</code>) — same pair updates the existing row.</li>
                <li>Drivers dedup on (<code>license_state</code>, <code>license_number</code>) when both present, else on <code>employee_id</code>, else a new row is created.</li>
            </ul>

            <h4>Special formats</h4>
            <ul>
                <li><strong>Dates</strong> — <code>YYYY-MM-DD</code>. Spreadsheet programs often reformat — verify before uploading.</li>
                <li><strong>Endorsements</strong> (drivers) — pipe or comma separated: <code>P|S</code> or <code>P,S,H</code>. Case-insensitive.</li>
                <li><strong>Enums</strong> — must match exactly: status = <code>active|inactive|on_leave</code>, fuel_type = <code>diesel|gasoline|propane|electric|hybrid</code>, etc.</li>
            </ul>
        </div>
    </x-filament::section>

    {{-- Setup & deployment --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Setup &amp; deployment</x-slot>
        <x-slot name="description">Getting edufleet running on a server.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>What's in the box</h4>
            <p>Docker Compose stack with four services:</p>
            <ul>
                <li><strong>app</strong> — Laravel 13 + Filament 5 on FrankenPHP (Caddy + PHP 8.4).</li>
                <li><strong>worker</strong> — <code>php artisan queue:work</code> for imports, audit-log writes, and other queued jobs. <em>Required</em> for CSV import to actually process.</li>
                <li><strong>db</strong> — PostgreSQL 16.</li>
                <li><strong>redis</strong> — Redis 7 for cache + queue.</li>
            </ul>

            <h4>First-time install</h4>
            <ol>
                <li>Clone the repo, copy <code>.env.example</code> to <code>.env</code>, set <code>APP_KEY</code>, <code>POSTGRES_PASSWORD</code>, and <code>APP_URL</code> (must be your final public URL).</li>
                <li><code>docker compose up -d</code> — builds and starts all four services.</li>
                <li><code>docker compose exec app php artisan migrate</code> — creates tables.</li>
                <li><code>docker compose exec app php artisan db:seed</code> — loads roles + permissions. (Does <em>not</em> load demo data.)</li>
                <li>Create the first super-admin:
                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs">docker compose exec app php artisan tinker --execute="
\$u = \App\Models\User::create([
  'name' => 'Jane Admin',
  'email' => 'admin@district.k12.state.us',
  'password' => bcrypt('CHANGE-ME'),
]);
\$u->assignRole('super-admin');
"</pre>
                </li>
                <li>Browse to your APP_URL, log in, change the password.</li>
            </ol>

            <h4>Behind a reverse proxy</h4>
            <p>If the container is fronted by Nginx Proxy Manager, Traefik, Caddy, or any reverse proxy doing TLS termination:</p>
            <ul>
                <li>The app already trusts proxies at <code>*</code> via <code>bootstrap/app.php</code> — required for signed URLs (quicktrip PWA, CSV import callbacks) to validate correctly.</li>
                <li>Set <code>APP_URL</code> to the <em>public HTTPS</em> URL, not the internal Docker one.</li>
                <li>Keep <code>URL::forceScheme('https')</code> in <code>AppServiceProvider</code> enabled for production.</li>
            </ul>

            <h4>Environment variables</h4>
            <ul>
                <li><code>APP_URL</code> — public HTTPS URL. Signed URLs fail if wrong.</li>
                <li><code>APP_KEY</code> — generate with <code>php artisan key:generate</code>.</li>
                <li><code>QUEUE_CONNECTION=redis</code> — required for background imports to run on the worker container.</li>
                <li><code>DB_*</code>, <code>REDIS_*</code> — default values in <code>.env.example</code> match the compose services.</li>
                <li><code>SERVER_NAME</code> — Caddy's listening hostname; <code>:80</code> for a proxy, or your public hostname for direct.</li>
            </ul>

            <h4>Backups</h4>
            <p>Two things to back up:</p>
            <ul>
                <li><strong>Database</strong> — nightly <code>pg_dump</code> of the <code>edufleet</code> database.
                    <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs">docker compose exec db pg_dump -U edufleet edufleet | gzip &gt; backup-$(date +%F).sql.gz</pre>
                </li>
                <li><strong>Uploaded files</strong> — the <code>src/storage/app</code> folder (imports, QR codes, any future document attachments).</li>
            </ul>
        </div>
    </x-filament::section>

    {{-- Ops & maintenance --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Ops &amp; maintenance</x-slot>
        <x-slot name="description">Keeping the app healthy.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <h4>Updating the app</h4>
            <ol>
                <li><code>git pull</code> in the host repo.</li>
                <li><code>docker compose build app worker</code> if PHP deps changed.</li>
                <li><code>docker compose up -d</code>.</li>
                <li><code>docker compose exec app php artisan migrate --force</code>.</li>
                <li><code>docker compose exec app php artisan optimize:clear</code>.</li>
                <li><code>docker compose restart worker</code> (so the worker loads the new code).</li>
            </ol>

            <h4>Logs</h4>
            <ul>
                <li>App log: <code>docker compose exec app tail -f storage/logs/laravel.log</code></li>
                <li>Worker: <code>docker compose logs -f worker</code></li>
                <li>Caddy / HTTP: <code>docker compose logs -f app</code></li>
            </ul>

            <h4>Clearing caches</h4>
            <p>If config or views don't reflect a change, from the host:</p>
            <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs">docker compose exec app php artisan optimize:clear</pre>

            <h4>Queue worker health</h4>
            <p>If imports or background jobs sit forever without finishing, the worker has probably stalled. Check <code>docker compose ps</code> — <code>edufleet-worker</code> should be <em>Up</em>. Restart with <code>docker compose restart worker</code>.</p>

            <h4>Database console</h4>
            <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs">docker compose exec db psql -U edufleet edufleet</pre>

            <h4>Permission cache</h4>
            <p>Role / permission changes take effect immediately, but if they appear to be "sticky", clear:</p>
            <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs">docker compose exec app php artisan permission:cache-reset</pre>
        </div>
    </x-filament::section>

    {{-- KSDE basics --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">KSDE transportation reimbursement — the basics</x-slot>
        <x-slot name="description">Kansas State Department of Education pays districts for student transportation based on a formula — not every trip counts.</x-slot>

        <div class="prose prose-sm dark:prose-invert max-w-none">
            <ul>
                <li><strong>Eligible rider</strong> — a student living <strong>2.5 miles or more</strong> from their attendance center (or inside 2.5 mi if on a board-approved hazardous route). These are the students the state pays the district to transport.</li>
                <li><strong>Ineligible rider</strong> — a student living inside 2.5 mi without a hazardous-route designation. Districts commonly still pick them up (courtesy rider), but <em>the state does not reimburse for their miles</em>.</li>
                <li><strong>Daily-route trips</strong> (trip_type = <code>daily_route</code>) are the only trips that feed the KSDE claim. Athletic, field, activity, and maintenance trips are paid from other funds.</li>
                <li><strong>Rider-miles</strong> — the report column and KSDE reimbursement basis. For each trip it's <code>miles × eligible riders</code>; for the whole period it's the sum of those.</li>
                <li>Run the report at <a href="{{ \App\Filament\Pages\KsdeReport::getUrl() }}" class="text-primary-600 hover:underline">KSDE mileage report</a> — pick a date range, review rollups, export CSV.</li>
            </ul>
        </div>
    </x-filament::section>

    {{-- Expiration color legend --}}
    <x-filament::section>
        <x-slot name="heading">Expiration color legend</x-slot>
        <x-slot name="description">All expiration columns (CDL, DOT medical, inspections, registrations, maintenance due) follow the same color rules.</x-slot>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-danger-500/30 bg-danger-50 p-3 dark:bg-danger-500/10">
                <div class="font-semibold text-danger-700 dark:text-danger-300">Red — expired</div>
                <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">Already past the expiration date. Driver should not operate / vehicle should not move.</div>
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

    {{-- Glossary --}}
    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Glossary</x-slot>

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
                <dt class="font-semibold">Restrictions (L, Z, 1…)</dt>
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
                <dd class="text-sm text-gray-600 dark:text-gray-400">A trip with <code>started_at</code> but no <code>ended_at</code>. Completing it (adding end_odometer + ended_at) auto-bumps the vehicle's odometer.</dd>
            </div>
            <div>
                <dt class="font-semibold">Rider-miles</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">For each trip: <code>miles × eligible riders</code>. Summed across a reporting period, this is the KSDE reimbursement basis.</dd>
            </div>
            <div>
                <dt class="font-semibold">Reservation lifecycle</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400"><code>requested</code> → <code>reserved</code> → <code>claimed</code> → <code>returned</code>. Side paths: <code>denied</code>, <code>cancelled</code>, <code>expired</code>.</dd>
            </div>
            <div>
                <dt class="font-semibold">Split request</dt>
                <dd class="text-sm text-gray-600 dark:text-gray-400">A single teacher request approved across multiple vehicles (e.g. 20 passengers in two 12-pax vans). All legs share a <code>split_group_id</code>.</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
