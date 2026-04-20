<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i a')
                    ->sortable(),

                TextColumn::make('causer')
                    ->label('Who')
                    ->formatStateUsing(function (Activity $record) {
                        $c = $record->causer;
                        return $c?->name ?? $c?->email ?? '— system —';
                    })
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHasMorph(
                        'causer',
                        [\App\Models\User::class],
                        fn (Builder $uq) => $uq->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%")
                    )),

                TextColumn::make('log_name')
                    ->label('Area')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'vehicle' => 'info',
                        'driver' => 'success',
                        'trip' => 'warning',
                        'route' => 'primary',
                        'inspection', 'registration', 'maintenance' => 'gray',
                        'user' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->formatStateUsing(function (Activity $record) {
                        $s = $record->subject;
                        if (! $s) {
                            return class_basename($record->subject_type) . ' #' . $record->subject_id . ' (deleted)';
                        }
                        return match (true) {
                            method_exists($s, 'getActivityLogLabel') => $s->getActivityLogLabel(),
                            property_exists($s, 'unit_number') || isset($s->unit_number) => 'Vehicle ' . $s->unit_number,
                            isset($s->last_name) && isset($s->first_name) => "{$s->last_name}, {$s->first_name}",
                            isset($s->code) && isset($s->name) => "{$s->code} ({$s->name})",
                            isset($s->name) => $s->name,
                            isset($s->email) => $s->email,
                            default => class_basename($record->subject_type) . ' #' . $s->id,
                        };
                    }),

                TextColumn::make('attribute_changes')
                    ->label('Changes')
                    ->formatStateUsing(function ($state, Activity $record) {
                        $changes = $record->attribute_changes;
                        if (! $changes || $changes->isEmpty()) return '—';

                        $attrs = (array) $changes->get('attributes', []);
                        $old   = (array) $changes->get('old', []);
                        $keys  = array_keys($attrs ?: $old);
                        if (empty($keys)) return '—';

                        $preview = array_slice($keys, 0, 4);
                        $more    = count($keys) - count($preview);
                        return implode(', ', $preview) . ($more > 0 ? " +{$more} more" : '');
                    })
                    ->tooltip(fn (Activity $record) => json_encode($record->attribute_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: null)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Area')
                    ->options([
                        'vehicle' => 'Vehicle',
                        'driver' => 'Driver',
                        'trip' => 'Trip',
                        'route' => 'Route',
                        'inspection' => 'Inspection',
                        'registration' => 'Registration',
                        'maintenance' => 'Maintenance',
                        'user' => 'User',
                    ]),
                SelectFilter::make('description')
                    ->label('Action')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                        'restored' => 'Restored',
                    ]),
                Filter::make('last_7_days')
                    ->label('Last 7 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7))),
                Filter::make('last_30_days')
                    ->label('Last 30 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(30))),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Audit log entry')
                    ->infolist([
                        \Filament\Infolists\Components\TextEntry::make('created_at')->dateTime(),
                        \Filament\Infolists\Components\TextEntry::make('causer.name')->label('User')->placeholder('— system —'),
                        \Filament\Infolists\Components\TextEntry::make('causer.email')->label('Email')->placeholder('—'),
                        \Filament\Infolists\Components\TextEntry::make('log_name')->badge(),
                        \Filament\Infolists\Components\TextEntry::make('description')->badge(),
                        \Filament\Infolists\Components\TextEntry::make('subject_type')->label('Subject')
                            ->formatStateUsing(fn (?string $state, Activity $record) => ($state ? class_basename($state) : '—') . ' #' . $record->subject_id),
                        \Filament\Infolists\Components\TextEntry::make('attribute_changes')
                            ->label('Attribute changes')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('properties')
                            ->label('Extra properties')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                            ->columnSpanFull(),
                    ]),
            ])
            ->bulkActions([]);
    }
}
