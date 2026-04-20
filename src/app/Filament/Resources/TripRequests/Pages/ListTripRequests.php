<?php

namespace App\Filament\Resources\TripRequests\Pages;

use App\Filament\Resources\TripRequests\TripRequestResource;
use App\Models\TripReservation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTripRequests extends ListRecords
{
    protected static string $resource = TripRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = fn () => TripRequestResource::getEloquentQuery();

        $pendingCount  = (clone $baseQuery())->where('status', TripReservation::STATUS_REQUESTED)->count();
        $upcomingCount = (clone $baseQuery())->whereIn('status', [TripReservation::STATUS_RESERVED, TripReservation::STATUS_CLAIMED])->count();

        return [
            'pending' => Tab::make('Pending review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TripReservation::STATUS_REQUESTED))
                ->badge($pendingCount > 0 ? $pendingCount : null)
                ->badgeColor('warning'),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    TripReservation::STATUS_RESERVED,
                    TripReservation::STATUS_CLAIMED,
                ]))
                ->badge($upcomingCount > 0 ? $upcomingCount : null)
                ->badgeColor('info'),

            'history' => Tab::make('History')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    TripReservation::STATUS_RETURNED,
                    TripReservation::STATUS_DENIED,
                    TripReservation::STATUS_CANCELLED,
                    TripReservation::STATUS_EXPIRED,
                ])),

            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        // Admins default to the Pending review queue; teachers default to "All"
        // since their list is smaller and mixing statuses is useful for them.
        $user = auth()->user();
        if ($user && method_exists($user, 'isTeacherOnly') && $user->isTeacherOnly()) {
            return 'all';
        }
        return 'pending';
    }
}
