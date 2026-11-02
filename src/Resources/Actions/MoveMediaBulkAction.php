<?php

namespace TomatoPHP\FilamentMediaManager\Resources\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use TomatoPHP\FilamentMediaManager\Models\Folder;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource;
use TomatoPHP\FilamentMediaManager\Resources\MediaResource\Pages\ListMedia;
use TomatoPHP\FilamentMediaManager\Services\MediaMover;

class MoveMediaBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('moveMedia')
            ->label(trans('filament-media-manager::messages.media.actions.move.label'))
            ->icon('heroicon-o-folder-arrow-down')
            ->color('gray')
            // Not in a search, whose media are from several folders, not from the open one
            ->visible(fn ($livewire): bool => ! ($livewire instanceof ListMedia && $livewire->searchesAllFolders())
                && ($folder = static::currentFolder()) && MediaMover::canMoveFrom($folder))
            ->authorizeIndividualRecords(fn (Model $record): Response => MediaResource::getEditAuthorizationResponse($record))
            ->schema([
                Select::make('folder')
                    ->label(trans('filament-media-manager::messages.media.actions.move.folder'))
                    ->options(fn (): array => MediaMover::targets(static::currentFolder())->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (iterable $records, array $data): void {
                $from = static::currentFolder();

                // The options are only offered, the browser can send any id
                $to = $from ? MediaMover::targets($from)->find($data['folder']) : null;

                if (! $to || ! MediaMover::canMoveFrom($from)) {
                    return;
                }

                $count = MediaMover::move($records, $to);

                Notification::make()
                    ->title(trans_choice('filament-media-manager::messages.media.notifications.moved', $count, ['count' => $count, 'folder' => $to->name]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function currentFolder(): ?Folder
    {
        return Folder::query()->find(session('folder_id'));
    }
}
