<?php

namespace App\Filament\Resources\PreTripInspections;

use App\Filament\Concerns\AuthorizesViaSpatie;
use App\Filament\Resources\PreTripInspections\Pages\ListPreTripInspections;
use App\Filament\Resources\PreTripInspections\Pages\ViewPreTripInspection;
use App\Filament\Resources\PreTripInspections\Schemas\PreTripInspectionInfolist;
use App\Filament\Resources\PreTripInspections\Tables\PreTripInspectionsTable;
use App\Models\PreTripInspection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PreTripInspectionResource extends Resource
{
    use AuthorizesViaSpatie;

    public static function getPermissionPrefix(): string
    {
        return 'pre_trip_inspection';
    }

    protected static ?string $model = PreTripInspection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'pre-trip inspection';

    protected static ?string $pluralModelLabel = 'pre-trip inspections';

    /** Read-only — create/update happens via Quicktrip, not admin. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PreTripInspectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PreTripInspectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreTripInspections::route('/'),
            'view' => ViewPreTripInspection::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
