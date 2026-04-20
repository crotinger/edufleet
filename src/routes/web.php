<?php

use App\Livewire\QuickTrip;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public PWA quicktrip flow — driver scans QR on vehicle. Protected by:
//   - signed URL (ValidateSignature middleware)
//   - rate limit per vehicle (prevents spam if a QR photo leaks)
//   - PIN check inside the Livewire component
Route::get('/quicktrip/{vehicle}', QuickTrip::class)
    ->middleware(['signed', 'throttle:quicktrip'])
    ->name('quicktrip');
