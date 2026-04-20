<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i a')
                    ->sortable(),

                TextColumn::make('route.code')
                    ->label('Route')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('vehicle.unit_number')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('driver')
                    ->label('Driver')
                    ->formatStateUsing(fn (Trip $record) => $record->display_driver_name)
                    ->description(fn (Trip $r) => $r->driver_id === null && $r->driver_name_override ? 'guest (quicktrip)' : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('driver_name_override', 'ilike', "%{$search}%")
                        ->orWhereHas('driver', fn (Builder $dq) => $dq
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"))),

                TextColumn::make('trip_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Trip::types()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Trip::TYPE_DAILY_ROUTE => 'success',
                        Trip::TYPE_ATHLETIC, Trip::TYPE_ACTIVITY => 'info',
                        Trip::TYPE_FIELD_TRIP => 'warning',
                        Trip::TYPE_MAINTENANCE => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Trip::statuses()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Trip::STATUS_APPROVED => 'success',
                        Trip::STATUS_PENDING => 'warning',
                        Trip::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->tooltip(fn (Trip $r) => $r->status === Trip::STATUS_REJECTED && $r->rejection_reason ? "Rejected: {$r->rejection_reason}" : null),

                TextColumn::make('purpose')->wrap()->toggleable(),

                TextColumn::make('miles')
                    ->label('Miles')
                    ->numeric()
                    ->badge()
                    ->color(fn (Trip $r) => $r->ended_at ? 'success' : 'warning')
                    ->formatStateUsing(fn (Trip $r) => $r->miles !== null ? number_format($r->miles) . ' mi' : 'in progress'),

                TextColumn::make('durationMinutes')
                    ->label('Duration')
                    ->formatStateUsing(function (Trip $r) {
                        $m = $r->duration_minutes;
                        if ($m === null) return '—';
                        $h = intdiv($m, 60);
                        $rem = $m % 60;
                        return $h > 0 ? "{$h}h {$rem}m" : "{$rem}m";
                    })
                    ->toggleable(),

                TextColumn::make('passengers')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('riders_eligible')->label('Eligible')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('riders_ineligible')->label('Ineligible')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('start_odometer')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end_odometer')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('trip_type')->options(Trip::types()),
                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'unit_number')
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->relationship('driver', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Driver $d) => "{$d->last_name}, {$d->first_name}")
                    ->searchable()
                    ->preload(),
                SelectFilter::make('route_id')
                    ->label('Route')
                    ->relationship('route', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Route $r) => "{$r->code} — {$r->name}")
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(Trip::statuses())
                    ->default(null),
                Filter::make('pending_only')
                    ->label('Awaiting approval')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('status', Trip::STATUS_PENDING)),
                Filter::make('in_progress')
                    ->label('In progress')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNull('ended_at')),
                Filter::make('this_week')
                    ->label('This week')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereBetween('started_at', [now()->startOfWeek(), now()->endOfWeek()])),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Trip $r) => $r->status === Trip::STATUS_PENDING
                        && auth()->user()?->hasAnyRole(['super-admin', 'transportation-director']))
                    ->requiresConfirmation()
                    ->modalHeading('Approve this trip?')
                    ->modalDescription(fn (Trip $r) => "Once approved, this trip counts toward KSDE totals and appears in all dashboard widgets. Driver: {$r->display_driver_name}, miles: " . ($r->miles ?? 'n/a'))
                    ->action(function (Trip $r) {
                        $r->update([
                            'status' => Trip::STATUS_APPROVED,
                            'approved_at' => now(),
                            'approved_by_user_id' => auth()->id(),
                            'rejection_reason' => null,
                        ]);
                        // Mark the linked reservation as returned
                        if ($r->reservation_id && $r->ended_at) {
                            $r->reservation?->update(['status' => \App\Models\TripReservation::STATUS_RETURNED]);
                        }
                        Notification::make()->title('Trip approved')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Trip $r) => $r->status === Trip::STATUS_PENDING
                        && auth()->user()?->hasAnyRole(['super-admin', 'transportation-director']))
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3)
                            ->placeholder('e.g. duplicate entry, incorrect odometer, unauthorized use'),
                    ])
                    ->action(function (Trip $r, array $data) {
                        $r->update([
                            'status' => Trip::STATUS_REJECTED,
                            'approved_at' => null,
                            'approved_by_user_id' => null,
                            'rejection_reason' => $data['rejection_reason'] ?? null,
                        ]);
                        if ($r->reservation_id) {
                            $r->reservation?->update(['status' => \App\Models\TripReservation::STATUS_CANCELLED]);
                        }
                        Notification::make()->title('Trip rejected')->danger()->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
