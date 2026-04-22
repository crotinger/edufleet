<?php

namespace App\Filament\Resources\InspectionTemplates;

use App\Filament\Concerns\AuthorizesViaSpatie;
use App\Filament\Resources\InspectionTemplates\Pages\CreateInspectionTemplate;
use App\Filament\Resources\InspectionTemplates\Pages\EditInspectionTemplate;
use App\Filament\Resources\InspectionTemplates\Pages\ListInspectionTemplates;
use App\Filament\Resources\InspectionTemplates\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\InspectionTemplates\Schemas\InspectionTemplateForm;
use App\Filament\Resources\InspectionTemplates\Tables\InspectionTemplatesTable;
use App\Models\InspectionTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InspectionTemplateResource extends Resource
{
    use AuthorizesViaSpatie;

    public static function getPermissionPrefix(): string
    {
        return 'inspection_template';
    }

    protected static ?string $model = InspectionTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'inspection template';

    public static function form(Schema $schema): Schema
    {
        return InspectionTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InspectionTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInspectionTemplates::route('/'),
            'create' => CreateInspectionTemplate::route('/create'),
            'edit' => EditInspectionTemplate::route('/{record}/edit'),
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
