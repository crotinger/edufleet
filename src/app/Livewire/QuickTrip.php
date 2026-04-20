<?php

namespace App\Livewire;

use App\Models\Trip;
use App\Models\TripReservation;
use App\Models\Vehicle;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.quicktrip')]
class QuickTrip extends Component
{
    public Vehicle $vehicle;

    public ?TripReservation $reservation = null;

    public ?Trip $openTrip = null;

    // Driver inputs
    public string $pin = '';
    public string $driver_name = '';
    public string $purpose = '';
    public string $trip_type = Trip::TYPE_ATHLETIC;
    public ?int $start_odometer = null;
    public ?int $end_odometer = null;
    public ?int $passengers = null;
    public ?int $riders_eligible = null;
    public ?int $riders_ineligible = null;

    // Disown flow — used when the open trip belongs to someone else who forgot to close it
    public ?int $disown_odometer = null;

    public ?string $flash = null;
    public ?string $flashKind = null;

    // 'start'  — no open trip (reservation may or may not exist); show start form
    // 'end'    — open trip exists; show end form
    // 'disown' — user says the open trip isn't theirs; collect odometer to close it out
    // 'done'   — just submitted
    public string $step = 'start';

    public function mount(Vehicle $vehicle): void
    {
        $this->vehicle = $vehicle;
        $this->loadState();
    }

    private function loadState(): void
    {
        $this->openTrip = Trip::where('vehicle_id', $this->vehicle->id)
            ->whereNull('ended_at')
            ->whereIn('status', [Trip::STATUS_PENDING, Trip::STATUS_APPROVED])
            ->whereNotNull('reservation_id')
            ->latest('started_at')
            ->first();

        if ($this->openTrip) {
            $this->step = 'end';
            $this->start_odometer = $this->openTrip->start_odometer;
            $this->passengers = $this->openTrip->passengers;
            $this->purpose = $this->openTrip->purpose ?? '';
            return;
        }

        $this->reservation = TripReservation::query()
            ->activeForVehicle($this->vehicle->id)
            ->first();

        if ($this->reservation) {
            $this->driver_name = $this->reservation->expected_driver_name ?? '';
            $this->purpose = $this->reservation->purpose;
            $this->trip_type = $this->reservation->planned_trip_type;
            $this->passengers = $this->reservation->expected_passengers;
        }
        $this->start_odometer = $this->vehicle->odometer_miles;
    }

    public function startTrip(): void
    {
        $data = $this->validate([
            'pin' => ['required', 'string', 'max:16'],
            'driver_name' => ['required', 'string', 'max:128'],
            'purpose' => ['required', 'string', 'max:191'],
            'trip_type' => ['required', 'string', 'in:' . implode(',', array_keys(Trip::types()))],
            'start_odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'passengers' => ['nullable', 'integer', 'min:0', 'max:120'],
        ]);

        if (trim($this->pin) !== (string) $this->vehicle->quicktrip_pin) {
            $this->addError('pin', 'Incorrect PIN. Check the label on the dashboard.');
            return;
        }

        if ($data['trip_type'] === Trip::TYPE_DAILY_ROUTE) {
            $this->addError('trip_type', 'Daily route trips are logged by district employees in the admin panel, not here.');
            return;
        }

        // If no admin-issued reservation exists, create a self-service one so
        // every trip has a reservation behind it (our audit invariant).
        if (! $this->reservation) {
            $this->reservation = TripReservation::create([
                'vehicle_id' => $this->vehicle->id,
                'source' => TripReservation::SOURCE_SELF_SERVICE,
                'purpose' => $data['purpose'],
                'planned_trip_type' => $data['trip_type'],
                'expected_driver_name' => $data['driver_name'],
                'expected_passengers' => $data['passengers'],
                'issued_at' => now(),
                'status' => TripReservation::STATUS_CLAIMED,
            ]);
        } else {
            $this->reservation->update(['status' => TripReservation::STATUS_CLAIMED]);
        }

        $trip = Trip::create([
            'vehicle_id' => $this->vehicle->id,
            'driver_id' => null,
            'driver_name_override' => $data['driver_name'],
            'trip_type' => $data['trip_type'],
            'purpose' => $data['purpose'],
            'started_at' => now(),
            'start_odometer' => $data['start_odometer'],
            'passengers' => $data['passengers'],
            'status' => Trip::STATUS_PENDING,
            'reservation_id' => $this->reservation->id,
        ]);

        $this->reservation->update(['trip_id' => $trip->id]);

        $this->openTrip = $trip->fresh();
        $this->step = 'end';
        $this->pin = '';
        $this->flash = "Trip started. Drive safe! When you return, rescan the same QR code to log the end of the trip.";
        $this->flashKind = 'info';
    }

    public function enterDisownFlow(): void
    {
        if (! $this->openTrip) {
            return;
        }
        $this->step = 'disown';
        $this->disown_odometer = null;
        $this->pin = '';
        $this->resetErrorBag();
    }

    public function cancelDisown(): void
    {
        $this->step = 'end';
        $this->disown_odometer = null;
        $this->resetErrorBag();
    }

    public function disownTrip(): void
    {
        if (! $this->openTrip) {
            $this->addError('general', 'No open trip to close.');
            return;
        }

        $data = $this->validate([
            'pin' => ['required', 'string', 'max:16'],
            'disown_odometer' => ['required', 'integer', 'min:' . ((int) $this->openTrip->start_odometer), 'max:9999999'],
        ], [
            'disown_odometer.min' => 'Odometer must be at least :min (the trip\'s start reading).',
        ]);

        if (trim($this->pin) !== (string) $this->vehicle->quicktrip_pin) {
            $this->addError('pin', 'Incorrect PIN.');
            return;
        }

        $flag = '[AUTO-CLOSED BY NEXT DRIVER on ' . now()->format('M j, Y g:i a') . '] '
            . 'Previous driver (' . ($this->openTrip->driver_name_override ?: 'unknown') . ') '
            . 'did not log end of trip. Closed here at odometer ' . number_format($this->disown_odometer) . '. '
            . 'End timestamp is an estimate — please review.';

        $existingNotes = trim((string) $this->openTrip->notes);

        $this->openTrip->update([
            'ended_at' => now(),
            'end_odometer' => $this->disown_odometer,
            'notes' => $existingNotes ? $flag . "\n\n" . $existingNotes : $flag,
        ]);

        // Release any still-claimed reservation so it doesn't haunt the next scan
        if ($this->openTrip->reservation_id) {
            $this->openTrip->reservation?->update([
                'status' => TripReservation::STATUS_RETURNED,
            ]);
        }

        // Reset the form for the next driver. They don't see Bob's reservation.
        $previousOdometer = $this->disown_odometer;
        $this->openTrip = null;
        $this->reservation = null;
        $this->disown_odometer = null;
        $this->pin = '';
        $this->driver_name = '';
        $this->purpose = '';
        $this->trip_type = Trip::TYPE_ATHLETIC;
        $this->passengers = null;
        $this->riders_eligible = null;
        $this->riders_ineligible = null;
        $this->end_odometer = null;
        $this->start_odometer = $previousOdometer;
        $this->step = 'start';
        $this->flash = "Previous driver's trip was closed and flagged for review. Start your trip below.";
        $this->flashKind = 'info';
    }

    public function endTrip(): void
    {
        if (! $this->openTrip) {
            $this->addError('general', 'No open trip found.');
            return;
        }

        $data = $this->validate([
            'pin' => ['required', 'string', 'max:16'],
            'end_odometer' => ['required', 'integer', 'min:' . ((int) $this->openTrip->start_odometer), 'max:9999999'],
            'passengers' => ['nullable', 'integer', 'min:0', 'max:120'],
            'riders_eligible' => ['nullable', 'integer', 'min:0', 'max:120'],
            'riders_ineligible' => ['nullable', 'integer', 'min:0', 'max:120'],
        ], [
            'end_odometer.min' => 'End odometer must be at least :min (where the trip started).',
        ]);

        if (trim($this->pin) !== (string) $this->vehicle->quicktrip_pin) {
            $this->addError('pin', 'Incorrect PIN.');
            return;
        }

        $this->openTrip->update([
            'ended_at' => now(),
            'end_odometer' => $data['end_odometer'],
            'passengers' => $data['passengers'] ?? $this->openTrip->passengers,
            'riders_eligible' => $data['riders_eligible'],
            'riders_ineligible' => $data['riders_ineligible'],
        ]);

        // Reservation stays 'claimed' until an admin approves the trip (then → returned).
        $this->step = 'done';
        $this->flash = "Trip submitted for review. Thanks — the transportation director will approve it shortly.";
        $this->flashKind = 'success';
    }

    public function render(): View
    {
        return view('livewire.quick-trip');
    }
}
