<?php

namespace App\Filament\RelationManagers;

use App\Models\Attachment;
use App\Models\Driver;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Documents';

    protected static ?string $recordTitleAttribute = 'original_name';

    public function form(Schema $schema): Schema
    {
        $owner = $this->getOwnerRecord();
        $ownerSlug = Str::kebab(class_basename($owner));
        $directory = "attachments/{$ownerSlug}/{$owner->getKey()}";

        return $schema->components([
            FileUpload::make('path')
                ->label('File')
                ->disk('local')
                ->visibility('private')
                ->directory($directory)
                ->preserveFilenames()
                ->storeFileNamesIn('original_name')
                ->maxSize(20 * 1024) // 20 MB
                ->required(),
            Grid::make(2)->schema([
                TextInput::make('label')
                    ->maxLength(128)
                    ->placeholder('e.g. Front of CDL, KHP 2025 sticker, shop invoice #1234'),
                Select::make('category')
                    ->options(Attachment::categories())
                    ->native(false),
            ]),
            Select::make('dqf_component')
                ->label('Driver Qualification File (DQF) component')
                ->helperText('Tag if this satisfies an FMCSA 49 CFR §391 Driver Qualification File requirement. Leave blank otherwise.')
                ->options(Attachment::dqfComponentLabels())
                ->native(false)
                ->visible(fn () => $owner instanceof Driver)
                ->columnSpanFull(),
            Textarea::make('description')->rows(2)->columnSpanFull(),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['disk'] = 'local';
        $data['uploaded_by_user_id'] = auth()->id();
        $this->populateFileMetadata($data);
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->populateFileMetadata($data);
        return $data;
    }

    private function populateFileMetadata(array &$data): void
    {
        $path = $data['path'] ?? null;
        if (! $path) {
            return;
        }
        $disk = Storage::disk($data['disk'] ?? 'local');
        if (! $disk->exists($path)) {
            return;
        }
        if (empty($data['original_name'])) {
            $data['original_name'] = basename($path);
        }
        $data['mime_type'] = $disk->mimeType($path) ?: ($data['mime_type'] ?? null);
        $data['size_bytes'] = $disk->size($path);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('original_name')
                    ->label('File')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Attachment $r) => $r->human_size . ($r->mime_type ? " · {$r->mime_type}" : '')),
                TextColumn::make('label')->searchable()->toggleable(),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (Attachment::categories()[$state] ?? $state) : '—')
                    ->color('info'),
                TextColumn::make('dqf_component')
                    ->label('DQF')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (?string $state) => $state ? (Attachment::dqfComponentLabels()[$state] ?? $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: ! ($this->getOwnerRecord() instanceof Driver))
                    ->visible(fn () => $this->getOwnerRecord() instanceof Driver),
                TextColumn::make('uploadedBy.name')->label('Uploaded by')->toggleable(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')->options(Attachment::categories()),
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()->label('Upload document')->icon(Heroicon::OutlinedArrowUpTray),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->url(fn (Attachment $record) => route('attachments.download', $record), shouldOpenInNewTab: true)
                    ->visible(fn (Attachment $record) => $record->exists()),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
