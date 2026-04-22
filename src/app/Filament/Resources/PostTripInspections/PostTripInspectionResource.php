<?php

namespace App\Filament\Resources\PostTripInspections;

use App\Filament\Concerns\AuthorizesViaSpatie;
use App\Filament\Resources\PostTripInspections\Pages\ListPostTripInspections;
use App\Filament\Resources\PostTripInspections\Pages\ViewPostTripInspection;
use App\Filament\Resources\PostTripInspections\Schemas\PostTripInspectionInfolist;
use App\Filament\Resources\PostTripInspections\Tables\PostTripInspectionsTable;
use App\Models\PostTripInspection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PostTripInspectionResource extends Resource
{
    use AuthorizesViaSpatie;

    public static function getPermissionPrefix(): string
    {
        return 'post_trip_inspection';
    }

    protected static ?string $model = PostTripInspection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 31;

    protected static ?string $modelLabel = 'post-trip inspection';

    protected static ?string $pluralModelLabel = 'post-trip inspections';

    public static function getNavigationBadge(): ?string
    {
        $count = PostTripInspection::query()
            ->where('defect_status', PostTripInspection::DEFECT_OPEN)
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Post-trip inspections with open defects';
    }

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
        return PostTripInspectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostTripInspectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostTripInspections::route('/'),
            'view' => ViewPostTripInspection::route('/{record}'),
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
