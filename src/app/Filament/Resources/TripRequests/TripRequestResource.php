<?php

namespace App\Filament\Resources\TripRequests;

use App\Filament\Concerns\AuthorizesViaSpatie;
use App\Filament\Resources\TripRequests\Pages\CreateTripRequest;
use App\Filament\Resources\TripRequests\Pages\EditTripRequest;
use App\Filament\Resources\TripRequests\Pages\ListTripRequests;
use App\Filament\Resources\TripRequests\Schemas\TripRequestForm;
use App\Filament\Resources\TripRequests\Tables\TripRequestsTable;
use App\Models\TripReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TripRequestResource extends Resource
{
    use AuthorizesViaSpatie;

    public static function getPermissionPrefix(): string
    {
        return 'trip_request';
    }

    protected static ?string $model = TripReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Vehicle requests';

    protected static ?string $modelLabel = 'request';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return TripRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TripRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTripRequests::route('/'),
            'create' => CreateTripRequest::route('/create'),
            'edit' => EditTripRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('source', TripReservation::SOURCE_TEACHER_REQUEST);

        $user = auth()->user();
        // Teachers see only their own requests; admins see all
        if ($user && method_exists($user, 'isTeacherOnly') && $user->isTeacherOnly()) {
            $query->where('requested_by_user_id', $user->id);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) return null;

        // For admins: show pending-to-approve count
        if ($user->hasAnyRole(['super-admin', 'transportation-director'])) {
            $count = TripReservation::requested()
                ->where('source', TripReservation::SOURCE_TEACHER_REQUEST)
                ->count();
            return $count > 0 ? (string) $count : null;
        }

        // For teachers: show count of their active requests
        $count = TripReservation::upcoming()
            ->where('source', TripReservation::SOURCE_TEACHER_REQUEST)
            ->where('requested_by_user_id', $user->id)
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Teachers can only edit their own request while it's still in 'requested'
     * status. Once approved, denied, etc., it becomes read-only for them.
     * Admins (super-admin / transportation-director) can always edit.
     */
    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isTeacherOnly') && $user->isTeacherOnly()) {
            return $record->requested_by_user_id === $user->id
                && $record->status === TripReservation::STATUS_REQUESTED;
        }

        return $user->can('update_trip_request');
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isTeacherOnly') && $user->isTeacherOnly()) {
            return $record->requested_by_user_id === $user->id
                && $record->status === TripReservation::STATUS_REQUESTED;
        }

        return $user->can('delete_trip_request');
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();
        return $user !== null && $user->can('delete_trip_request');
    }
}
