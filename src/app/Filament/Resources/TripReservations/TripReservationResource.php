<?php

namespace App\Filament\Resources\TripReservations;

use App\Filament\Resources\TripReservations\Pages\CreateTripReservation;
use App\Filament\Resources\TripReservations\Pages\EditTripReservation;
use App\Filament\Resources\TripReservations\Pages\ListTripReservations;
use App\Filament\Resources\TripReservations\Schemas\TripReservationForm;
use App\Filament\Resources\TripReservations\Tables\TripReservationsTable;
use App\Models\TripReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripReservationResource extends Resource
{
    protected static ?string $model = TripReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Key reservations';

    protected static ?string $modelLabel = 'reservation';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return TripReservationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TripReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTripReservations::route('/'),
            'create' => CreateTripReservation::route('/create'),
            'edit' => EditTripReservation::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canViewAny(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'viewer']);
    }

    public static function canCreate(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director']);
    }

    public static function canEdit($record): bool
    {
        return static::canCreate();
    }

    public static function canDelete($record): bool
    {
        return static::canCreate();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TripReservation::whereIn('status', [TripReservation::STATUS_RESERVED, TripReservation::STATUS_CLAIMED])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
