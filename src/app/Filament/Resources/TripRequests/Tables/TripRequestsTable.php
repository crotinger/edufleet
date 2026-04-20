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
                    ->placeholder('—')
                    ->description(function (TripReservation $r) {
                        if (! $r->split_group_id) return null;
                        $all = $r->allSplitLegs();
                        if ($all->count() <= 1) return null;
                        $units = $all->map(fn ($leg) => $leg->vehicle?->unit_number)->filter()->implode(', ');
                        return 'Split of ' . $all->count() . ': ' . $units;
                    }),
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
                    ->modalHeading('Approve & assign vehicle(s)')
                    ->modalDescription(fn (TripReservation $r) => $r->desired_start_at
                        ? 'Requested for ' . $r->desired_start_at->format('M j, Y g:i a')
                            . ($r->expected_return_at ? ' – ' . $r->expected_return_at->format('g:i a') : '')
                            . ' · ' . $r->expected_passengers . ' passengers'
                        : 'Assign one or more vehicles and approve.')
                    ->visible(fn (TripReservation $r) => $r->status === TripReservation::STATUS_REQUESTED
                        && auth()->user()?->can('approve_trip_request'))
                    ->schema([
                        Select::make('vehicle_ids')
                            ->label('Assign vehicle(s)')
                            ->helperText('Pick one vehicle for a normal approval, or multiple to split a large group across several vehicles. Each selected vehicle gets its own reservation; all are linked as a split group.')
                            ->multiple()
                            ->options(fn () => Vehicle::query()
                                ->where('status', Vehicle::STATUS_ACTIVE)
                                ->orderBy('type')->orderBy('unit_number')
                                ->get()
                                ->mapWithKeys(fn (Vehicle $v) => [
                                    $v->id => "{$v->unit_number} · "
                                        . (Vehicle::types()[$v->type] ?? $v->type)
                                        . ($v->capacity_passengers ? " · seats {$v->capacity_passengers}" : ''),
                                ])
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->minItems(1)
                            ->live()
                            ->default(fn (TripReservation $r) => $r->vehicle_id ? [$r->vehicle_id] : []),

                        SchemaView::make('filament.partials.approval-checks')
                            ->viewData(function (Get $get, TripReservation $r) {
                                $ids = (array) ($get('vehicle_ids') ?? []);
                                if (empty($ids)) return ['issues' => []];

                                // For splits, also compute per-vehicle capacity + the combined seats check
                                $allIssues = [];
                                foreach ($ids as $vid) {
                                    $issuesForThis = self::approvalIssuesFor($r, (int) $vid);
                                    foreach ($issuesForThis as $issue) {
                                        $allIssues[] = $issue;
                                    }
                                }

                                // Combined capacity check (for splits only — if 1 vehicle, the per-vehicle
                                // check already covers it)
                                if (count($ids) > 1 && $r->expected_passengers) {
                                    $totalSeats = Vehicle::whereIn('id', $ids)->sum('capacity_passengers');
                                    if ($totalSeats > 0 && $r->expected_passengers > $totalSeats) {
                                        $allIssues[] = "Combined capacity: request is for {$r->expected_passengers} passengers, the {$totalSeats} combined seats across selected vehicles is not enough.";
                                    }
                                }

                                return ['issues' => $allIssues];
                            }),

                        Checkbox::make('force_override')
                            ->label('Approve anyway (override warnings above)')
                            ->helperText('Only check this when the warnings are acceptable / resolved out-of-band. A note is added to every affected reservation for the audit trail.')
                            ->default(false),
                    ])
                    ->action(function (TripReservation $r, array $data) {
                        $vehicleIds = array_values(array_filter(array_map('intval', (array) ($data['vehicle_ids'] ?? []))));

                        if (empty($vehicleIds)) {
                            Notification::make()->title('No vehicle selected')->danger()->send();
                            return;
                        }

                        // Collect issues across all vehicles + the combined-capacity check
                        $allIssues = [];
                        foreach ($vehicleIds as $vid) {
                            foreach (self::approvalIssuesFor($r, $vid) as $i) {
                                $allIssues[] = $i;
                            }
                        }
                        if (count($vehicleIds) > 1 && $r->expected_passengers) {
                            $totalSeats = Vehicle::whereIn('id', $vehicleIds)->sum('capacity_passengers');
                            if ($totalSeats > 0 && $r->expected_passengers > $totalSeats) {
                                $allIssues[] = "Combined capacity: request is for {$r->expected_passengers} passengers, the {$totalSeats} combined seats across selected vehicles is not enough.";
                            }
                        }

                        if (! empty($allIssues) && empty($data['force_override'])) {
                            Notification::make()
                                ->title('Approval blocked — ' . count($allIssues) . ' issue' . (count($allIssues) === 1 ? '' : 's'))
                                ->body(implode("\n\n", $allIssues))
                                ->danger()
                                ->persistent()
                                ->send();
                            return;
                        }

                        $noteAppend = null;
                        if (! empty($allIssues)) {
                            $noteAppend = '[APPROVED OVER WARNINGS on ' . now()->format('M j, Y g:i a') . ' by '
                                . (auth()->user()?->name ?? 'unknown') . ']' . PHP_EOL . implode(PHP_EOL, $allIssues);
                        }

                        $isSplit = count($vehicleIds) > 1;
                        $splitGroupId = $isSplit ? (string) \Illuminate\Support\Str::uuid() : null;
                        $now = now();

                        // Per-vehicle passenger allocation when splitting: divide as evenly
                        // as possible, with any remainder landing on the first legs.
                        $paxTotal = (int) ($r->expected_passengers ?? 0);
                        $paxAllocation = [];
                        if ($isSplit && $paxTotal > 0) {
                            $base = intdiv($paxTotal, count($vehicleIds));
                            $remainder = $paxTotal - ($base * count($vehicleIds));
                            foreach ($vehicleIds as $idx => $_) {
                                $paxAllocation[$idx] = $base + ($idx < $remainder ? 1 : 0);
                            }
                        }

                        $appendNoteTo = function ($res) use ($noteAppend) {
                            if (! $noteAppend) return $res->notes;
                            return trim(($res->notes ? $res->notes . PHP_EOL . PHP_EOL : '') . $noteAppend);
                        };

                        // Primary leg: update the original request in place.
                        $r->update([
                            'status' => TripReservation::STATUS_RESERVED,
                            'vehicle_id' => $vehicleIds[0],
                            'issued_at' => $now,
                            'issued_by_user_id' => auth()->id(),
                            'denied_reason' => null,
                            'denied_at' => null,
                            'denied_by_user_id' => null,
                            'split_group_id' => $splitGroupId,
                            'expected_passengers' => $isSplit && $paxTotal > 0 ? $paxAllocation[0] : $r->expected_passengers,
                            'notes' => $appendNoteTo($r),
                        ]);

                        // Additional legs: clone the request into new reservations with
                        // source=admin_issue (the additional vehicles are issued by the admin,
                        // not by the teacher's original request).
                        foreach (array_slice($vehicleIds, 1) as $idx => $vehicleId) {
                            $leg = TripReservation::create([
                                'vehicle_id' => $vehicleId,
                                'source' => TripReservation::SOURCE_ADMIN_ISSUE,
                                'status' => TripReservation::STATUS_RESERVED,
                                'purpose' => $r->purpose,
                                'planned_trip_type' => $r->planned_trip_type,
                                'expected_driver_name' => $r->expected_driver_name,
                                'expected_passengers' => $paxAllocation[$idx + 1] ?? $r->expected_passengers,
                                'desired_start_at' => $r->desired_start_at,
                                'expected_return_at' => $r->expected_return_at,
                                'preferred_vehicle_type' => $r->preferred_vehicle_type,
                                'requested_by_user_id' => $r->requested_by_user_id,
                                'issued_at' => $now,
                                'issued_by_user_id' => auth()->id(),
                                'split_group_id' => $splitGroupId,
                                'split_parent_request_id' => $r->id,
                                'notes' => $noteAppend,
                            ]);
                        }

                        $title = match (true) {
                            $noteAppend && $isSplit => 'Split-approved over warnings',
                            $isSplit => "Approved across " . count($vehicleIds) . " vehicles",
                            $noteAppend => 'Approved over warnings',
                            default => 'Request approved',
                        };

                        Notification::make()
                            ->title($title)
                            ->body($isSplit ? 'Created ' . count($vehicleIds) . ' linked reservations, one per vehicle.' : null)
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
                $issues[] = "Reservation conflict: Unit {$vehicle->unit_number} has "
                    . $conflicts->count() . " overlapping reservation"
                    . ($conflicts->count() === 1 ? '' : 's')
                    . " — {$summary}{$more}";
            }

            // Also check standalone Trip records (no reservation_id) — these come
            // from admin-created trips or self-service quicktrips that bypass the
            // reservation conflict check above. An in-progress trip (ended_at null)
            // counts as occupying the vehicle indefinitely.
            $conflictingTrips = \App\Models\Trip::query()
                ->where('vehicle_id', $vehicleId)
                ->whereNull('reservation_id')
                ->whereIn('status', [
                    \App\Models\Trip::STATUS_APPROVED,
                    \App\Models\Trip::STATUS_PENDING,
                ])
                ->where('started_at', '<', $end)
                ->where(function ($q) use ($start) {
                    $q->whereNull('ended_at')->orWhere('ended_at', '>', $start);
                })
                ->orderBy('started_at')
                ->get();

            if ($conflictingTrips->isNotEmpty()) {
                $summary = $conflictingTrips->take(3)->map(function ($t) {
                    $startStr = $t->started_at?->format('M j g:i a') ?? '?';
                    $endStr = $t->ended_at?->format('g:i a') ?? 'in progress';
                    return "\"{$t->purpose}\" {$startStr} – {$endStr}";
                })->implode('; ');
                $more = $conflictingTrips->count() > 3 ? " · +" . ($conflictingTrips->count() - 3) . " more" : '';
                $issues[] = "Trip conflict: Unit {$vehicle->unit_number} has "
                    . $conflictingTrips->count() . " overlapping trip"
                    . ($conflictingTrips->count() === 1 ? '' : 's')
                    . " logged directly (no reservation) — {$summary}{$more}";
            }
        }

        return $issues;
    }
}
