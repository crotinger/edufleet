<?php

namespace App\Filament\Widgets;

use App\Models\Trip;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class TripMilesByType extends TableWidget
{
    protected static ?string $heading = 'Trip miles by type — this month';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'viewer']);
    }

    public function table(Table $table): Table
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $sub = DB::table('trips')
            ->selectRaw('
                MIN(id) as id,
                trip_type,
                COUNT(*) as trips_count,
                COALESCE(SUM(end_odometer - start_odometer), 0) as miles_sum,
                COALESCE(SUM(riders_eligible), 0) as eligible_sum,
                COALESCE(SUM(riders_ineligible), 0) as ineligible_sum,
                COALESCE(SUM(passengers), 0) as passengers_sum,
                NULL::timestamp as deleted_at
            ')
            ->whereNotNull('ended_at')
            ->where('status', Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end])
            ->whereNull('deleted_at')
            ->groupBy('trip_type');

        return $table
            ->query(
                Trip::query()
                    ->withoutGlobalScopes()
                    ->fromSub($sub, 'trips')
            )
            ->defaultSort('miles_sum', 'desc')
            ->paginated(false)
            ->columns([
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
                    }),
                TextColumn::make('trips_count')
                    ->label('Trips')
                    ->numeric(),
                TextColumn::make('miles_sum')
                    ->label('Miles')
                    ->formatStateUsing(fn ($state) => number_format((int) $state))
                    ->sortable(),
                TextColumn::make('passengers_sum')
                    ->label('Total passengers')
                    ->formatStateUsing(fn ($state) => number_format((int) $state))
                    ->toggleable(),
                TextColumn::make('eligible_sum')
                    ->label('Eligible')
                    ->formatStateUsing(fn ($state) => number_format((int) $state)),
                TextColumn::make('ineligible_sum')
                    ->label('Ineligible')
                    ->formatStateUsing(fn ($state) => number_format((int) $state))
                    ->toggleable(),
            ]);
    }
}
