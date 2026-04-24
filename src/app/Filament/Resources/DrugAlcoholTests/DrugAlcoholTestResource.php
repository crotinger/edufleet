<?php

namespace App\Filament\Resources\DrugAlcoholTests;

use App\Filament\Concerns\AuthorizesViaSpatie;
use App\Filament\Resources\DrugAlcoholTests\Pages\CreateDrugAlcoholTest;
use App\Filament\Resources\DrugAlcoholTests\Pages\EditDrugAlcoholTest;
use App\Filament\Resources\DrugAlcoholTests\Pages\ListDrugAlcoholTests;
use App\Filament\Resources\DrugAlcoholTests\Schemas\DrugAlcoholTestForm;
use App\Filament\Resources\DrugAlcoholTests\Tables\DrugAlcoholTestsTable;
use App\Models\DrugAlcoholTest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DrugAlcoholTestResource extends Resource
{
    use AuthorizesViaSpatie;

    public static function getPermissionPrefix(): string
    {
        return 'drug_alcohol_test';
    }

    protected static ?string $model = DrugAlcoholTest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 33;

    protected static ?string $modelLabel = 'drug & alcohol test';

    protected static ?string $pluralModelLabel = 'drug & alcohol tests';

    public static function getNavigationBadge(): ?string
    {
        $open = DrugAlcoholTest::query()
            ->whereNotNull('scheduled_for')
            ->whereNull('completed_on')
            ->count();
        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Selections awaiting completion';
    }

    public static function form(Schema $schema): Schema
    {
        return DrugAlcoholTestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrugAlcoholTestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\RelationManagers\AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrugAlcoholTests::route('/'),
            'create' => CreateDrugAlcoholTest::route('/create'),
            'edit' => EditDrugAlcoholTest::route('/{record}/edit'),
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
