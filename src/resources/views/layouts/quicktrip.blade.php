<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    <title>{{ $title ?? 'edufleet · Quick trip' }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            -webkit-tap-highlight-color: transparent;
        }
        .qt-wrap {
            max-width: 28rem;
            margin: 0 auto;
            padding: 1rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .qt-header {
            padding: 1.25rem 0 0.75rem;
            text-align: center;
        }
        .qt-header h1 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: -0.01em;
        }
        .qt-header .sub {
            margin-top: 0.15rem;
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .qt-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .qt-vehicle {
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
            border: 1px solid #93c5fd;
            border-radius: 0.875rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1rem;
        }
        .qt-vehicle .unit {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e3a8a;
            line-height: 1;
        }
        .qt-vehicle .meta {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            color: #1e40af;
        }
        .qt-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
            margin-bottom: 0.25rem;
        }
        .qt-input, .qt-select, .qt-textarea {
            width: 100%;
            padding: 0.75rem 0.875rem;
            font-size: 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.625rem;
            background: white;
            color: #0f172a;
            appearance: none;
            -webkit-appearance: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .qt-input:focus, .qt-select:focus, .qt-textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        .qt-input[readonly] { background: #f1f5f9; color: #475569; }
        .qt-field { margin-bottom: 0.875rem; }
        .qt-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .qt-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.625rem; }
        .qt-btn {
            display: block; width: 100%;
            padding: 1rem;
            font-size: 1.0625rem;
            font-weight: 600;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background-color 0.15s, transform 0.1s;
        }
        .qt-btn-primary { background: #2563eb; color: white; }
        .qt-btn-primary:hover { background: #1d4ed8; }
        .qt-btn-primary:active { transform: scale(0.98); }
        .qt-btn-success { background: #059669; color: white; }
        .qt-btn-success:hover { background: #047857; }
        .qt-error {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            color: #b91c1c;
        }
        .qt-flash {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.625rem;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .qt-flash-info { background: #dbeafe; border: 1px solid #93c5fd; color: #1e3a8a; }
        .qt-flash-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #064e3b; }
        .qt-badge {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-radius: 0.375rem;
            background: #dbeafe;
            color: #1e40af;
        }
        .qt-badge-green { background: #d1fae5; color: #047857; }
        .qt-badge-orange { background: #fed7aa; color: #9a3412; }
        .qt-helper {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        .qt-foot {
            text-align: center;
            padding: 1.5rem 0 1rem;
            font-size: 0.6875rem;
            color: #94a3b8;
        }
        .qt-summary {
            background: #f1f5f9;
            border-radius: 0.625rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .qt-summary dt { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; font-weight: 600; }
        .qt-summary dd { margin: 0.125rem 0 0.5rem 0; font-size: 0.9375rem; color: #0f172a; }
        .qt-summary dd:last-child { margin-bottom: 0; }
    </style>
    @livewireStyles
</head>
<body>
    <div class="qt-wrap">
        <div class="qt-header">
            <h1>edufleet · Quick Trip</h1>
            <div class="sub">Volunteer / ad-hoc trip logger</div>
        </div>

        {{ $slot }}

        <div class="qt-foot">
            edufleet · USD444 transportation
        </div>
    </div>
    @livewireScripts
</body>
</html>
