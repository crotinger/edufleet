<?php

use App\Http\Controllers\AttachmentController;
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

// Attachment downloads — auth-only; the controller additionally checks
// that the user has at least one role (i.e. any staff account).
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('attachments.download');
