<?php

namespace App\Filament\Widgets;

use App\Models\Inspection;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingExpirationsTable extends TableWidget
{
    protected static ?string $heading = 'Next vehicle inspections due';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'mechanic', 'viewer']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Inspection::query()
                    ->whereNotNull('expires_on')
                    ->where('expires_on', '<=', now()->addDays(90)->toDateString())
                    ->orderBy('expires_on')
            )
            ->defaultPaginationPageOption(10)
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('vehicle.unit_number')
                    ->label('Unit')
                    ->searchable(),
                TextColumn::make('vehicle.type')
                    ->label('Vehicle type')
                    ->formatStateUsing(fn (?string $state) => \App\Models\Vehicle::types()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('type')
                    ->label('Inspection')
                    ->formatStateUsing(fn (?string $state) => Inspection::types()[$state] ?? $state)
                    ->badge(),
                TextColumn::make('expires_on')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(function (?string $state) {
                        if (! $state) return null;
                        $d = \Carbon\Carbon::parse($state);
                        return match (true) {
                            $d->isPast() => 'danger',
                            $d->diffInDays(now(), false) > -30 => 'warning',
                            default => 'info',
                        };
                    })
                    ->description(function (?string $state) {
                        if (! $state) return null;
                        $d = \Carbon\Carbon::parse($state);
                        if ($d->isPast()) return 'Overdue ' . $d->diffForHumans();
                        $days = (int) ceil(now()->floatDiffInDays($d));
                        return "In {$days} day" . ($days === 1 ? '' : 's');
                    }),
                TextColumn::make('result')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Inspection::results()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Inspection::RESULT_PASSED => 'success',
                        Inspection::RESULT_PASSED_WITH_DEFECTS => 'warning',
                        Inspection::RESULT_FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('inspector_name')->label('Inspector')->toggleable(),
            ]);
    }
}
