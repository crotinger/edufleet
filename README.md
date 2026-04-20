# edufleet

Fleet management app for a small K-12 school district — buses, light vehicles, drivers, routes, trips, inspections, registrations, maintenance, and a state transportation-reimbursement report. Built for a small district that lives or dies by clean reimbursement numbers and knowing which vehicle is where at any moment.

Primary goals:

- Give the transportation director a single place to log daily-route trips, issue vehicles to volunteer drivers, review pending submissions, and produce the state transportation reimbursement claim.
- Let teachers request vehicles without needing admin panel access, and let parent volunteers log athletic / field-trip miles from a phone QR code inside the cab.
- Keep a defensible audit trail (who changed what, when) to survive state audits.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Admin UI | Filament 5 (+ Livewire 3) |
| Database | PostgreSQL 16 |
| Cache / sessions / queues | Redis 7 |
| Web server / runtime | FrankenPHP (Caddy + PHP embedded) |
| Containerization | Docker Compose |
| Auth / RBAC | `spatie/laravel-permission` |
| Audit log | `spatie/laravel-activitylog` |
| QR codes | `bacon/bacon-qr-code` |

Target deploy: a Proxmox VM (or any small cloud VM) behind Nginx Proxy Manager (or any TLS-terminating reverse proxy). Everything runs as three containers from a single `compose.yaml` — portable between environments.

## Features

- **Vehicles, Drivers, Routes** — full CRUD with relation managers and expiration tracking (CDL, DOT medical, inspections, registrations)
- **Trips** — daily routes + athletic / field / activity / maintenance runs, with odometer auto-sync to the Vehicle when a trip completes
- **Vehicle requests (teacher-facing)** — teachers submit a request; admin approves with a vehicle assignment (capacity + scheduling-conflict detection, override with audit note)
- **Key reservations** — secretary scans a key-fob barcode to issue keys for a trip
- **Quicktrip (PWA)** — single signed-URL QR code per vehicle. Volunteers scan, enter PIN, log start and end odometers. Pending submissions go to a review queue before they count toward totals
- **Review queue** — all quicktrip and teacher-request submissions land as `status=pending` and don't count toward reimbursement totals or dashboard widgets until an admin approves them
- **Mileage report** — date-range filter, per-route / per-vehicle / per-type rollups, data-quality checks (missing end odometers, silent routes, odometer regressions), CSV export, optional rate-per-mile estimator
- **Vehicle availability** — sortable sheet-style view showing each vehicle's current state (available / in use / reserved) + next 14 days
- **Reservation schedule** — week grid of vehicles × days with prev / this / next week navigation
- **Dashboard** — six widgets (expirations, trip mileage, upcoming inspections, maintenance due, ridership by route, trips by type) + three chart widgets (miles/week, trips by type, reimbursable mix)
- **Audit log** — append-only trail of every create / update / delete across all major models
- **Help page** — in-app glossary of domain terms, roles, color conventions, common workflows

## First-run (fresh Ubuntu 24.04 host)

```bash
# 1) Install Docker Engine + Compose plugin (root)
sudo apt-get update && sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update && sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker $USER
newgrp docker

# 2) Clone
sudo mkdir -p /opt/edufleet && sudo chown $USER:$USER /opt/edufleet
cd /opt/edufleet
git clone https://github.com/crotinger/edufleet .

# 3) Configure
cp .env.example .env
#   edit .env and set a real POSTGRES_PASSWORD, SERVER_NAME, APP_HTTP_PORT, APP_HTTPS_PORT

# 4) Build + launch
docker compose up -d --build

# 5) First-time Laravel bootstrap
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=RolesAndPermissionsSeeder --force

# 6) Create your first admin
docker compose exec app php artisan make:filament-user
#   follow prompts, then:
docker compose exec app php artisan tinker --execute="\
  \App\Models\User::where('email','<your-email>')->first()->assignRole('super-admin');"

# 7) (Optional) Populate realistic demo data
docker compose exec app php artisan db:seed --class=DemoSeeder --force
```

Now reach the admin panel at whatever `SERVER_NAME` / port you set.

### Running behind a TLS-terminating reverse proxy

Two things you must do to avoid `403 Invalid Signature` on the quicktrip QR flow:

1. Set `APP_URL` in `src/.env` to the **public HTTPS URL** (e.g. `https://edufleet.example.org`)
2. `bootstrap/app.php` already calls `->trustProxies(at: '*')` — keep it, and make sure your proxy forwards `X-Forwarded-Proto: https`

## Everyday commands

From `/opt/edufleet`:

```bash
# status / logs
docker compose ps
docker compose logs -f app

# start / stop
docker compose up -d
docker compose down

# artisan commands (migrations, tinker, seed, clear cache)
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose exec app php artisan optimize:clear

# composer (install a new package)
docker compose exec app composer require vendor/package
```

If you just ran `usermod -aG docker $USER` and haven't logged out and back in, prefix docker commands with `sg docker -c "..."` to pick up the group without re-logging.

## Users, roles, permissions

Every route to the admin panel requires one of these roles. Permissions are set in `database/seeders/RolesAndPermissionsSeeder.php`:

| Role | What they do | What they can see |
|---|---|---|
| `super-admin` | IT / primary administrator | Everything. Bypass all permission checks. Manage users & roles. |
| `transportation-director` | Runs transportation department day-to-day | Full CRUD on vehicles, drivers, trips, routes, inspections, registrations, maintenance, reservations. Approves teacher requests and quicktrip submissions. Sees audit log. Read-only on user management. |
| `mechanic` | Shop / maintenance crew | CRUD on vehicles, inspections, maintenance records. Read-only on trips. |
| `driver` | Bus driver who logs trips | Read-only on vehicles & routes. View + create **their own** trips (Driver field is locked to them; cannot see other drivers' trips). |
| `teacher` | Teacher requesting a vehicle | Submit vehicle requests, view own requests + status. See vehicle availability calendar. |
| `viewer` | Auditors, superintendents, board members | Read-only on everything. No create / edit / delete. |

A user can hold multiple roles. Driver-only users (just `driver`, no other role) get row-level scoping on Trips.

### Initial default passwords

`RolesAndPermissionsSeeder` + `DemoSeeder` create these for local / demo use only:

| Email | Password | Role |
|---|---|---|
| `admin@edufleet.local` | `ChangeMe-edufleet!2026` | super-admin |
| `mech@edufleet.local` | `Mech-edufleet-2026!` | mechanic |
| `driver@edufleet.local` | `Driv-edufleet-2026!` | driver |
| `teacher@edufleet.local` | `Teach-edufleet-2026!` | teacher |
| `viewer@edufleet.local` | `View-edufleet-2026!` | viewer |

**Rotate all of these before anything leaves your LAN.** They're intentionally printable + memorable for development, not production.

## Architecture highlights

### Quicktrip (PWA) flow

`GET /quicktrip/{vehicle}` is a public Livewire page behind `signed` + `throttle:quicktrip` middleware. Each vehicle has a `quicktrip_pin` (4-digit, printed on a sticker next to the QR). The flow is state-aware:

- **No open trip + active reservation found** → prefilled START form
- **No open trip + no reservation** → blank START form that creates a self-service reservation on submit
- **Open trip exists** → END form (odometer + ridership + PIN)
- **Open trip belongs to someone else** → "Not my trip" path closes the stale trip at the current odometer, flags it for review, then starts fresh

Every quicktrip-submitted trip is `status=pending` until an admin approves it. Until approval, it doesn't count toward reimbursement totals or any dashboard widget.

### Approval / audit model

Every aggregation query (mileage report, dashboard widgets, per-route / per-vehicle rollups) filters `trips.status = 'approved'`. Pending trips stay invisible to the reimbursement numbers but are visible in the main Trips list (with filter tabs + action buttons for approve / reject).

`spatie/laravel-activitylog` is wired on every major model (Vehicle, Driver, Trip, Route, Inspection, Registration, MaintenanceRecord, User, TripReservation). Each save writes a row to `activity_log` with the causer (user), the changed attributes, and old + new values. Viewable at `/admin/activity-logs`.

### Odometer auto-sync

When a Trip saves with a new `end_odometer` higher than the linked Vehicle's current reading, a `Trip::saved` hook bumps `vehicles.odometer_miles` (via `saveQuietly` to avoid re-triggering activity log noise). The maintenance widget's "miles to go" and the vehicle edit page always reflect the most recent trip without any manual reconciliation.

## Repo layout

```
/opt/edufleet
├── compose.yaml              # 3 containers: app (FrankenPHP+Caddy), db (Postgres), redis
├── .env                      # DB password, ports — NOT committed
├── .env.example              # template
├── docker/
│   └── app/
│       ├── Dockerfile        # PHP 8.4 + extensions + Caddyfile
│       └── Caddyfile
└── src/                      # Laravel app root
    ├── app/
    │   ├── Filament/         # Admin panel: Resources, Pages, Widgets
    │   ├── Livewire/         # QuickTrip component (public PWA page)
    │   ├── Models/           # Vehicle, Driver, Trip, Route, Inspection, Registration, MaintenanceRecord, TripReservation, User
    │   └── Providers/
    ├── database/
    │   ├── migrations/
    │   └── seeders/          # RolesAndPermissionsSeeder, DemoSeeder
    ├── resources/
    │   └── views/
    │       ├── filament/     # Custom Filament page blade templates
    │       ├── layouts/      # Quicktrip PWA layout (no admin chrome)
    │       └── livewire/     # Quicktrip component view
    ├── routes/
    │   └── web.php           # Registers /quicktrip/{vehicle} with signed + throttle
    └── ...
```

## Caveats & known limitations

- **No email notifications.** Teachers don't get emailed when requests are approved or denied. Add a Laravel notification + mail config if needed.
- **No calendar export.** Reservations don't push to Google Calendar / iCal.
- **Single reservation per vehicle at a time in the quicktrip state machine.** If multiple reservations for the same vehicle overlap, the scan only sees the oldest active one.
- **`ended_at` on a disowned trip is the next driver's scan time,** not the actual return time. The audit note makes this explicit but the director should adjust if they know the real return time.
- **PDF export is browser Print → Save as PDF** from the mileage report page. Server-side PDF (with letterhead) would need `dompdf` or `browsershot`.
- **Demo seeder is not idempotent about `users`.** Re-running wipes then re-creates test users. Safe for demo; don't run against production data.
- **SSO not implemented.** All auth is local username/password. Socialite + Google Workspace / Microsoft 365 is a future addition.

## Roadmap / next steps

- Login hardening (rate limit, 2FA, stronger password policy)
- Richer reimbursement formula (weighted pupil counts × cost index) — currently a back-of-envelope $/mile estimator
- Conflict detection between teacher requests and existing Trip records (currently only checks reservations)
- Month-view calendar (currently just week-view)
- Native mobile PWA install prompts

## License

[MIT](LICENSE) — Copyright (c) 2026 Jason Crotinger.
