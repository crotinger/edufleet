<?php

namespace App\Filament\Resources\TripRequests\Tables;

use App\Models\Trip;
use App\Models\TripReservation;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class TripRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('desired_start_at', 'asc')
            ->columns([
                TextColumn::make('desired_start_at')
                    ->label('Needed')
                    ->dateTime('M j, g:i a')
                    ->sortable()
                    ->description(fn (TripReservation $r) => $r->expected_return_at ? 'back by ' . $r->expected_return_at->format('M j g:i a') : null),
                TextColumn::make('purpose')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('planned_trip_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Trip::types()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Trip::TYPE_FIELD_TRIP => 'warning',
                        Trip::TYPE_ATHLETIC, Trip::TYPE_ACTIVITY => 'info',
                        Trip::TYPE_MAINTENANCE => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('expected_passengers')->label('Pax')->numeric(),
                TextColumn::make('preferred_vehicle_type')
                    ->label('Prefers')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Vehicle::TYPE_BUS => 'Bus',
                        Vehicle::TYPE_LIGHT => 'Light',
                        default => 'any',
                    })
                    ->color(fn (?string $state) => $state ? 'info' : 'gray')
                    ->toggleable(),
                TextColumn::make('requestedBy.name')
                    ->label('Requested by')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('vehicle.unit_number')
                    ->label('Assigned')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => TripReservation::statuses()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        TripReservation::STATUS_REQUESTED => 'warning',
                        TripReservation::STATUS_RESERVED => 'success',
                        TripReservation::STATUS_CLAIMED => 'info',
                        TripReservation::STATUS_RETURNED => 'success',
                        TripReservation::STATUS_DENIED, TripReservation::STATUS_CANCELLED => 'danger',
                        TripReservation::STATUS_EXPIRED => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (TripReservation $r) => $r->status === TripReservation::STATUS_DENIED && $r->denied_reason ? 'Denied: ' . $r->denied_reason : null)
                    ->sortable(),
                TextColumn::make('created_at')->label('Submitted')->dateTime('M j, g:i a')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(TripReservation::statuses())->default(null),
                SelectFilter::make('planned_trip_type')->label('Type')->options(Trip::types()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading('Approve & assign vehicle')
                    ->modalDescription(fn (TripReservation $r) => $r->desired_start_at
                        ? 'Requested for ' . $r->desired_start_at->format('M j, Y g:i a')
                            . ($r->expected_return_at ? ' – ' . $r->expected_return_at->format('g:i a') : '')
                            . ' · ' . $r->expected_passengers . ' passengers'
                        : 'Assign a vehicle and approve.')
                    ->visible(fn (TripReservation $r) => $r->status === TripReservation::STATUS_REQUESTED
                        && auth()->user()?->can('approve_trip_request'))
                    ->schema([
                        Select::make('vehicle_id')
                            ->label('Assign vehicle')
                            ->relationship('vehicle', 'unit_number')
                            ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · "
                                . (Vehicle::types()[$v->type] ?? $v->type)
                                . ($v->capacity_passengers ? " · seats {$v->capacity_passengers}" : ''))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->default(fn (TripReservation $r) => $r->vehicle_id),

                        SchemaView::make('filament.partials.approval-checks')
                            ->viewData(fn (Get $get, TripReservation $r) => [
                                'issues' => self::approvalIssuesFor($r, $get('vehicle_id') ? (int) $get('vehicle_id') : null),
                            ]),

                        Checkbox::make('force_override')
                            ->label('Approve anyway (override warnings above)')
                            ->helperText('Only check this when the warnings are acceptable / resolved out-of-band. A note is added to the reservation for the audit trail.')
                            ->default(false),
                    ])
                    ->action(function (TripReservation $r, array $data) {
                        $vehicleId = (int) $data['vehicle_id'];
                        $issues = self::approvalIssuesFor($r, $vehicleId);

                        if (! empty($issues) && empty($data['force_override'])) {
                            Notification::make()
                                ->title('Approval blocked — ' . count($issues) . ' issue' . (count($issues) === 1 ? '' : 's'))
                                ->body(implode("\n\n", $issues))
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        $noteAppend = null;
                        if (! empty($issues)) {
                            $noteAppend = '[APPROVED OVER WARNINGS on ' . now()->format('M j, Y g:i a') . ' by '
                                . (auth()->user()?->name ?? 'unknown') . ']' . PHP_EOL . implode(PHP_EOL, $issues);
                        }

                        $r->update([
                            'status' => TripReservation::STATUS_RESERVED,
                            'vehicle_id' => $vehicleId,
                            'issued_at' => now(),
                            'issued_by_user_id' => auth()->id(),
                            'denied_reason' => null,
                            'denied_at' => null,
                            'denied_by_user_id' => null,
                            'notes' => $noteAppend
                                ? trim(($r->notes ? $r->notes . PHP_EOL . PHP_EOL : '') . $noteAppend)
                                : $r->notes,
                        ]);

                        Notification::make()
                            ->title($noteAppend ? 'Approved over warnings' : 'Request approved')
                            ->body($noteAppend ? 'Conflicts or capacity issues were overridden — note added to reservation.' : null)
                            ->color($noteAppend ? 'warning' : 'success')
                            ->send();
                    }),

                Action::make('deny')
                    ->label('Deny')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TripReservation $r) => $r->status === TripReservation::STATUS_REQUESTED
                        && auth()->user()?->can('deny_trip_request'))
                    ->schema([
                        Textarea::make('denied_reason')
                            ->label('Reason (shown to the teacher)')
                            ->required()
                            ->rows(3)
                            ->placeholder('e.g. no vehicles of that type available that day; scheduling conflict; etc.'),
                    ])
                    ->action(function (TripReservation $r, array $data) {
                        $r->update([
                            'status' => TripReservation::STATUS_DENIED,
                            'denied_reason' => $data['denied_reason'],
                            'denied_at' => now(),
                            'denied_by_user_id' => auth()->id(),
                        ]);
                        Notification::make()->title('Request denied')->danger()->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Return a list of human-readable warnings for approving this request against
     * the chosen vehicle. Empty array = no issues. Checks:
     *   - capacity: request's passenger count vs vehicle's capacity_passengers
     *   - scheduling: any other reservation whose window overlaps ours
     *
     * @return array<int, string>
     */
    public static function approvalIssuesFor(TripReservation $r, ?int $vehicleId): array
    {
        if (! $vehicleId) {
            return [];
        }

        $issues = [];
        $vehicle = Vehicle::find($vehicleId);
        if (! $vehicle) {
            return ['Vehicle not found.'];
        }

        // 1. Passenger capacity check
        $cap = $vehicle->capacity_passengers;
        if ($cap && $r->expected_passengers && $r->expected_passengers > $cap) {
            $over = $r->expected_passengers - $cap;
            $issues[] = "Capacity: request is for {$r->expected_passengers} passengers, Unit {$vehicle->unit_number} seats {$cap} (over by {$over}).";
        }

        // 2. Scheduling overlap check (only if we have a time window)
        if ($r->desired_start_at && $r->expected_return_at) {
            $start = $r->desired_start_at;
            $end = $r->expected_return_at;

            $conflicts = TripReservation::query()
                ->where('vehicle_id', $vehicleId)
                ->where('id', '!=', $r->id)
                ->whereIn('status', [
                    TripReservation::STATUS_REQUESTED,
                    TripReservation::STATUS_RESERVED,
                    TripReservation::STATUS_CLAIMED,
                ])
                ->where(function ($q) use ($start, $end) {
                    // Their start must be before our end (or their start is null)
                    $q->where(function ($inner) use ($end) {
                        $inner->whereNull('desired_start_at')
                              ->orWhere('desired_start_at', '<', $end);
                    })
                    // AND their end must be after our start (or their end is null)
                    ->where(function ($inner) use ($start) {
                        $inner->whereNull('expected_return_at')
                              ->orWhere('expected_return_at', '>', $start);
                    });
                })
                ->orderBy('desired_start_at')
                ->get();

            if ($conflicts->isNotEmpty()) {
                $summary = $conflicts->take(3)->map(function ($c) {
                    $startStr = $c->desired_start_at?->format('M j g:i a') ?? '(no start set)';
                    $endStr = $c->expected_return_at?->format('g:i a') ?? '?';
                    return "\"{$c->purpose}\" {$startStr} – {$endStr} [{$c->status}]";
                })->implode('; ');
                $more = $conflicts->count() > 3 ? " · +" . ($conflicts->count() - 3) . " more" : '';
                $issues[] = "Scheduling conflict: Unit {$vehicle->unit_number} has "
                    . $conflicts->count() . " overlapping reservation"
                    . ($conflicts->count() === 1 ? '' : 's')
                    . " — {$summary}{$more}";
            }
        }

        return $issues;
    }
}
