<?php

namespace App\Filament\Widgets;

use App\Models\Route;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class RidershipByRoute extends TableWidget
{
    protected static ?string $heading = 'Ridership by route — this month';

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

        $tripScope = fn ($q) => $q->whereBetween('started_at', [$start, $end])->whereNotNull('ended_at')->where('status', \App\Models\Trip::STATUS_APPROVED);

        return $table
            ->query(
                Route::query()
                    ->withCount(['trips as trips_count' => $tripScope])
                    ->withSum(['trips as miles_sum' => $tripScope], DB::raw('end_odometer - start_odometer'))
                    ->withSum(['trips as eligible_sum' => $tripScope], 'riders_eligible')
                    ->withSum(['trips as ineligible_sum' => $tripScope], 'riders_ineligible')
                    ->orderBy('code')
            )
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('code')->sortable(),
                TextColumn::make('name')->wrap(),
                TextColumn::make('trips_count')->label('Trips')->numeric()->sortable(),
                TextColumn::make('miles_sum')
                    ->label('Miles')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((int) $state) : '0')
                    ->sortable(),
                TextColumn::make('eligible_sum')
                    ->label('Eligible riders')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((int) $state) : '0')
                    ->description(fn (Route $r) => $r->trips_count > 0
                        ? 'avg ' . round(((int) $r->eligible_sum) / $r->trips_count, 1) . '/trip'
                        : null)
                    ->sortable(),
                TextColumn::make('ineligible_sum')
                    ->label('Ineligible')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((int) $state) : '0')
                    ->toggleable(),
                TextColumn::make('rider_miles')
                    ->label('Rider-miles (eligible × miles)')
                    ->formatStateUsing(function (Route $r) {
                        $mi = (int) ($r->miles_sum ?? 0);
                        $el = (int) ($r->eligible_sum ?? 0);
                        return number_format($mi * $el);
                    })
                    ->description('eligible KSDE reimbursement basis'),
            ]);
    }
}
